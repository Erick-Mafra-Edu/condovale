"""Publica a API Laravel no InfinityFree por FTP.

Existe porque o plano gratuito do host não tem Git nem SSH: o único canal de
entrega é FTP. O envio é incremental — o script guarda o md5 de tudo o que já
subiu e, nas execuções seguintes, manda só o que mudou. É isso que mantém o uso
do FTP dentro do que o InfinityFree autoriza e transforma um deploy de milhares
de arquivos em alguns segundos.

    python tools/deploy-infinityfree.py --dry-run   # mostra o plano
    python tools/deploy-infinityfree.py             # publica
    python tools/deploy-infinityfree.py --force     # ignora o estado e reenvia
    python tools/deploy-infinityfree.py --prune     # apaga no servidor o que sumiu aqui

As credenciais ficam em .env.deploy, na raiz do repositório, fora do git.
"""

from __future__ import annotations

import argparse
import ftplib
import hashlib
import io
import json
import posixpath
import sys
import time
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
LARAVEL = REPO_ROOT / "laravel"
STATE_FILE = REPO_ROOT / "tools" / ".deploy-state.json"
# Escrito a cada lote enviado, para que dê para acompanhar o andamento
# sem depender de onde a saída do processo foi parar.
PROGRESS_FILE = REPO_ROOT / "tools" / ".deploy-progress.txt"
REMOTE_MANIFEST = ".deploy-manifest.json"

# O front estático do Nuxt é publicado dentro do public/ do Laravel para que a
# interface e a API dividam a mesma origem: sem isso o cookie de sessão
# precisaria de SameSite=None e a API precisaria liberar CORS.
FRONT_BUILD = REPO_ROOT / "frontend" / ".output" / "public"
FRONT_REMOTE_DIR = "public"

# Lista de INCLUSÃO: só o que está aqui sobe. Uma lista de exclusão erraria da
# forma clássica — um diretório novo que ninguém lembrou de excluir acabaria
# publicado, e neste projeto isso significaria expor tests/ ou o .env local.
INCLUDE_DIRS = [
    "app",
    "bootstrap",
    "config",
    "database",
    "lang",
    "public",
    "resources/views",
    "routes",
    "storage",
    "vendor",
]

INCLUDE_FILES = [".htaccess", "artisan", "composer.json", "composer.lock"]

# No servidor o arquivo de ambiente se chama .env, e não .env.production: com
# esse nome a aplicação sobe mesmo se o mod_env estiver desabilitado e o SetEnv
# do .htaccess não tiver efeito.
ENV_SOURCE = ".env.production"
ENV_TARGET = ".env"

FTP_TIMEOUT = 120
# Tentativas por arquivo. A primeira falha não é erro: é a sessão caindo, o que
# numa transferência de mais de uma hora acontece com frequência.
MAX_ATTEMPTS = 6
RECONNECT_DELAY_SECONDS = 5
# Espera entre tentativas de login, multiplicada pelo número da tentativa. É
# bem maior que a de reconexão porque o servidor só libera a vaga da conta
# depois que a sessão anterior, morta junto com o processo, expira sozinha.
LOGIN_RETRY_SECONDS = 30

SKIP_NAMES = {".DS_Store", "Thumbs.db", ".phpunit.result.cache", ".gitkeep"}
SKIP_SUFFIXES = (".log",)


def should_send(rel: str) -> bool:
    """Decide se um caminho relativo do laravel/ entra no envio."""
    name = posixpath.basename(rel)

    if name in SKIP_NAMES or rel.endswith(SKIP_SUFFIXES):
        return False

    # O banco de desenvolvimento não tem nada que fazer no servidor.
    if rel == "database/database.sqlite":
        return False

    # De storage/ e bootstrap/cache/ sobe apenas o .gitignore: ele é o que cria
    # os diretórios graváveis lá. O conteúdo é gerado em produção e sobrescrevê-lo
    # apagaria log e cache do servidor.
    if rel.startswith("storage/") or rel.startswith("bootstrap/cache/"):
        return name == ".gitignore"

    return True


def file_hash(path: Path) -> str:
    digest = hashlib.md5()

    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)

    return digest.hexdigest()


def load_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}

    if not path.exists():
        return values

    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()

        if not line or line.startswith("#") or "=" not in line:
            continue

        key, _, value = line.partition("=")
        values[key.strip()] = value.strip().strip('"').strip("'")

    return values


def collect_front() -> dict[str, Path]:
    """Mapeia o build estático do Nuxt para dentro do public/ do servidor."""
    files: dict[str, Path] = {}

    if not FRONT_BUILD.is_dir():
        return files

    for path in FRONT_BUILD.rglob("*"):
        if path.is_file():
            rel = path.relative_to(FRONT_BUILD).as_posix()
            files[f"{FRONT_REMOTE_DIR}/{rel}"] = path

    return files


def collect_local(include_front: bool = True) -> dict[str, Path]:
    """Mapeia caminho remoto -> arquivo local, já filtrado."""
    files: dict[str, Path] = {}

    for name in INCLUDE_FILES:
        path = LARAVEL / name

        if path.is_file():
            files[name] = path

    for directory in INCLUDE_DIRS:
        base = LARAVEL / directory

        if not base.is_dir():
            continue

        for path in base.rglob("*"):
            if not path.is_file():
                continue

            rel = path.relative_to(LARAVEL).as_posix()

            if should_send(rel):
                files[rel] = path

    env_source = LARAVEL / ENV_SOURCE

    if not env_source.is_file():
        raise SystemExit(
            f"{ENV_SOURCE} não encontrado em laravel/. Sem ele o servidor fica sem configuração."
        )

    files[ENV_TARGET] = env_source

    if include_front:
        front = collect_front()

        if front:
            files.update(front)
        else:
            print("Aviso: frontend/.output/public não existe — publicando só a API.")
            print("  cd frontend && NUXT_PUBLIC_DATA_SOURCE=api npx nuxt generate")

    return files


class Publisher:
    def __init__(self, config: dict[str, str], root: str, dry_run: bool) -> None:
        self.config = config
        self.root = root.strip("/")
        self.dry_run = dry_run
        self.known_dirs: set[str] = set()
        self.ftp: ftplib.FTP | None = None
        self.reconnections = 0

    def connect(self) -> None:
        """Abre a sessão, insistindo quando o host não atende.

        Logo depois de uma transferência longa o servidor costuma recusar ou
        ignorar a conexão seguinte por algum tempo, como se a sessão anterior
        ainda estivesse aberta. A espera cresce a cada tentativa para dar tempo
        de ele liberar a vaga.
        """
        for attempt in range(1, MAX_ATTEMPTS + 1):
            try:
                ftp = ftplib.FTP(timeout=FTP_TIMEOUT)
                ftp.encoding = "utf-8"
                ftp.connect(self.config["FTP_HOST"], int(self.config.get("FTP_PORT", 21)))
                ftp.login(self.config["FTP_USERNAME"], self.config["FTP_PASSWORD"])
                ftp.set_pasv(True)
                self.ftp = ftp

                return
            except ftplib.all_errors as error:
                if attempt == MAX_ATTEMPTS:
                    raise

                espera = LOGIN_RETRY_SECONDS * attempt
                print(f"  conexão falhou ({error}); nova tentativa em {espera}s ({attempt}/{MAX_ATTEMPTS - 1})")
                time.sleep(espera)

    def reconnect(self) -> None:
        """Refaz a sessão depois de uma queda.

        O host gratuito derruba sessões FTP longas, e uma publicação completa
        leva mais de uma hora — a queda no meio do caminho é o caso esperado,
        não a exceção. Os diretórios já criados continuam no servidor, então o
        cache de known_dirs segue valendo.
        """
        try:
            self.ftp.close()
        except Exception:
            pass

        self.reconnections += 1
        time.sleep(RECONNECT_DELAY_SECONDS)
        self.connect()

    def close(self) -> None:
        try:
            self.ftp.quit()
        except Exception:
            self.ftp.close()

    def remote_path(self, rel: str) -> str:
        return posixpath.join(self.root, rel) if self.root else rel

    def ensure_dir(self, rel_dir: str) -> None:
        if not rel_dir or rel_dir in self.known_dirs:
            return

        parent = posixpath.dirname(rel_dir)

        if parent:
            self.ensure_dir(parent)

        if not self.dry_run:
            try:
                self.ftp.mkd(self.remote_path(rel_dir))
            except ftplib.error_perm:
                # 550 quando o diretório já existe: é o caso normal.
                pass

        self.known_dirs.add(rel_dir)

    def upload(self, rel: str, path: Path) -> None:
        if self.dry_run:
            self.ensure_dir(posixpath.dirname(rel))
            return

        for attempt in range(1, MAX_ATTEMPTS + 1):
            try:
                self.ensure_dir(posixpath.dirname(rel))

                with path.open("rb") as handle:
                    self.ftp.storbinary(f"STOR {self.remote_path(rel)}", handle)

                return
            except ftplib.all_errors as error:
                if attempt == MAX_ATTEMPTS:
                    raise

                print(f"  queda em {rel}: {error}")
                print(f"  reconectando (tentativa {attempt} de {MAX_ATTEMPTS - 1})...")
                self.reconnect()

    def drop_derived_caches(self) -> None:
        """Apaga no servidor os arquivos que o Laravel deriva das dependências.

        bootstrap/cache/packages.php e services.php listam os service providers
        descobertos a partir de vendor/composer/installed.json. Quando o
        conjunto de dependências muda, eles ficam apontando para classes que o
        autoloader novo não conhece mais, e a aplicação inteira responde 500
        com "Class ... not found". Como não são enviados por este script — são
        derivados, não fonte —, a única forma de invalidá-los é removê-los, e o
        Laravel os reconstrói na requisição seguinte.
        """
        if self.dry_run:
            return

        for name in ("packages.php", "services.php"):
            try:
                self.ftp.delete(self.remote_path(f"bootstrap/cache/{name}"))
            except ftplib.all_errors:
                pass

    def delete(self, rel: str) -> None:
        if self.dry_run:
            return

        try:
            self.ftp.delete(self.remote_path(rel))
        except ftplib.error_perm:
            pass

    def fetch_manifest(self) -> dict[str, str]:
        buffer = io.BytesIO()

        try:
            self.ftp.retrbinary(f"RETR {self.remote_path(REMOTE_MANIFEST)}", buffer.write)
        except ftplib.all_errors:
            # Não existir é o caso normal na primeira publicação, e qualquer
            # outra falha aqui não deve derrubar o envio: quem manda no ponto de
            # retomada é o arquivo de estado local.
            return {}

        try:
            return json.loads(buffer.getvalue().decode("utf-8"))
        except ValueError:
            return {}

    def put_manifest(self, manifest: dict[str, str]) -> None:
        if self.dry_run:
            return

        payload = json.dumps(manifest, indent=0, sort_keys=True).encode("utf-8")
        self.ftp.storbinary(f"STOR {self.remote_path(REMOTE_MANIFEST)}", io.BytesIO(payload))


def main() -> int:
    parser = argparse.ArgumentParser(description="Publica a API Laravel no InfinityFree por FTP.")
    parser.add_argument("--dry-run", action="store_true", help="mostra o que faria, sem conectar para escrever")
    parser.add_argument("--force", action="store_true", help="ignora o estado anterior e reenvia tudo")
    parser.add_argument("--prune", action="store_true", help="apaga no servidor os arquivos que sumiram daqui")
    parser.add_argument("--allow-dev", action="store_true", help="publica mesmo com as dependências de desenvolvimento")
    parser.add_argument("--api-only", action="store_true", help="não envia o build do frontend")
    parser.add_argument(
        "--assume-synced",
        action="store_true",
        help="grava o manifesto como se o servidor já tivesse os arquivos locais, sem transferir nada",
    )
    parser.add_argument(
        "--only",
        action="append",
        default=[],
        metavar="CAMINHO",
        help="publica apenas o que começar por este caminho (pode repetir)",
    )
    args = parser.parse_args()

    # Subir o vendor/ com as dependências de desenvolvimento custa milhares de
    # arquivos na cota de inodes da conta e ainda coloca phpunit e faker no
    # servidor, que não têm o que fazer lá.
    if (LARAVEL / "vendor" / "phpunit").is_dir() and not args.allow_dev and not args.dry_run and not args.only:
        print("O vendor/ local tem dependências de desenvolvimento. Antes de publicar:")
        print("  cd laravel && composer install --no-dev --optimize-autoloader")
        print("  python tools/deploy-infinityfree.py")
        print("  cd laravel && composer install    # devolve o ambiente local para rodar os testes")
        print("Ou use --allow-dev para enviar assim mesmo.")
        return 1

    config = load_env(REPO_ROOT / ".env.deploy")
    missing = [k for k in ("FTP_HOST", "FTP_USERNAME", "FTP_PASSWORD") if not config.get(k)]

    if missing:
        print("Faltam credenciais em .env.deploy: " + ", ".join(missing))
        print("Copie .env.deploy.example para .env.deploy e preencha com os dados do painel.")
        return 1

    root = config.get("FTP_DIR", "htdocs")

    print("Lendo os arquivos locais...")
    local = collect_local(include_front=not args.api_only)

    if args.only:
        # Publicação pontual: corrigir uma regra de .htaccess não deveria custar
        # a releitura dos milhares de arquivos do vendor.
        local = {rel: path for rel, path in local.items() if any(rel.startswith(p) for p in args.only)}

        if not local:
            print("Nenhum arquivo corresponde a --only: " + ", ".join(args.only))
            return 1

    hashes = {rel: file_hash(path) for rel, path in local.items()}
    print(f"{len(local)} arquivos elegíveis em laravel/.")

    print(f"Conectando em {config['FTP_HOST']}...")
    publisher = Publisher(config, root, args.dry_run)
    publisher.connect()

    previous = publisher.fetch_manifest()

    if not previous and STATE_FILE.exists():
        previous = json.loads(STATE_FILE.read_text(encoding="utf-8"))

    if args.assume_synced:
        # Recuperação: o manifesto pode se desencontrar da realidade quando uma
        # publicação é interrompida. Em vez de reenviar milhares de arquivos
        # idênticos, registra-se o estado atual como já publicado.
        STATE_FILE.write_text(json.dumps(hashes, indent=0, sort_keys=True), encoding="utf-8")
        publisher.put_manifest(hashes)
        publisher.close()

        print(f"Manifesto regravado com {len(hashes)} arquivos, sem transferência.")

        return 0

    if args.force:
        # Com --only, o --force vale apenas para o recorte pedido: zerar o
        # manifesto inteiro faria o script esquecer os milhares de arquivos que
        # já estão no servidor e reenviar tudo na próxima publicação.
        previous = {
            rel: digest
            for rel, digest in previous.items()
            if not any(rel.startswith(prefix) for prefix in args.only)
        } if args.only else {}

    pending = [rel for rel, digest in sorted(hashes.items()) if previous.get(rel) != digest]

    # vendor/composer/ vai sempre, mesmo quando o hash bate com o manifesto.
    # São doze arquivos, e são eles que descrevem quais pacotes existem: se o
    # manifesto local se desencontrar do servidor — o que acontece quando uma
    # publicação é interrompida —, o script passa a pular justamente o arquivo
    # que derruba a aplicação inteira quando está errado.
    always = [rel for rel in sorted(hashes) if rel.startswith("vendor/composer/")]
    pending = sorted(set(pending) | set(always))

    # Com --only a lista local é um recorte, então o que está fora dele não é
    # obsoleto: é apenas o que não se pediu para publicar agora.
    obsolete = [] if args.only else sorted(set(previous) - set(hashes) - {REMOTE_MANIFEST})

    print(f"A enviar: {len(pending)} | inalterados: {len(local) - len(pending)} | sobrando no servidor: {len(obsolete)}")

    if args.dry_run:
        for rel in pending[:40]:
            print(f"  + {rel}")

        if len(pending) > 40:
            print(f"  ... e mais {len(pending) - 40}")

        for rel in obsolete[:20]:
            print(f"  - {rel}" + ("" if args.prune else "  (use --prune para apagar)"))

        publisher.close()
        return 0

    sent = previous.copy()
    started = time.monotonic()

    try:
        for index, rel in enumerate(pending, start=1):
            publisher.upload(rel, local[rel])
            sent[rel] = hashes[rel]

            if index % 10 == 0 or index == len(pending):
                # O estado é gravado durante o percurso: se a conexão cair, a
                # execução seguinte retoma de onde parou em vez de recomeçar.
                STATE_FILE.write_text(json.dumps(sent, indent=0, sort_keys=True), encoding="utf-8")

                elapsed = time.monotonic() - started
                rate = index / elapsed if elapsed else 0
                remaining = (len(pending) - index) / rate if rate else 0
                line = (
                    f"{index}/{len(pending)} ({index * 100 / len(pending):.1f}%)"
                    f" · {rate:.1f} arq/s · faltam ~{remaining / 60:.0f} min"
                )

                print(f"  {line}")
                PROGRESS_FILE.write_text(line, encoding="utf-8")

        if args.prune:
            for rel in obsolete:
                publisher.delete(rel)
                sent.pop(rel, None)

            print(f"{len(obsolete)} arquivos removidos do servidor.")
    finally:
        STATE_FILE.write_text(json.dumps(sent, indent=0, sort_keys=True), encoding="utf-8")

    publisher.drop_derived_caches()
    publisher.put_manifest(sent)
    publisher.close()

    print("Publicado.")

    if publisher.reconnections:
        print(f"A sessão FTP caiu e foi refeita {publisher.reconnections}x durante o envio.")

    print("Se o schema ainda não existe, rode a carga inicial:")
    print('  curl -X POST "$APP_URL/api/deploy/migrate" -H "X-Deploy-Token: <token>"')

    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except ftplib.all_errors as error:
        print(f"Erro de FTP: {error}")
        sys.exit(1)
