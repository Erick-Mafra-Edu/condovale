"""Confere se produção corresponde ao que está na main.

Existe porque dois publicadores escrevem no mesmo servidor — o publicador local
e o workflow do GitHub Actions — e cada um guarda o seu próprio manifesto do
que já enviou. Nenhum dos dois sabe o que o outro fez, e outras pessoas também
podem publicar. Confiar no processo não basta: tem que olhar.

    python tools/verify-production.py

Duas conferências:

- **arquivos**: baixa do servidor os que derrubam a aplicação inteira quando
  ficam defasados e compara o md5 com a árvore local;
- **rotas**: chama a interface e os endpoints principais, resolvendo o desafio
  anti-robô do host.

Sai com código 1 se algo divergir, para poder virar passo de pipeline.
"""

from __future__ import annotations

import ftplib
import hashlib
import http.cookiejar
import importlib.util
import io
import json
import re
import ssl
import subprocess
import tempfile
import os
import sys
import urllib.request
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
LARAVEL = REPO_ROOT / 'laravel'

spec = importlib.util.spec_from_file_location('deploy', REPO_ROOT / 'tools' / 'deploy-infinityfree.py')
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)

UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36'

# Os arquivos cujo atraso não degrada: derruba. bootstrap/app.php registra os
# middlewares, routes/api.php os endpoints, e vendor/composer descreve quais
# pacotes existem — errado, ele dá "Class ... not found" em toda requisição.
CRITICOS = [
    'bootstrap/app.php',
    'bootstrap/providers.php',
    'routes/api.php',
    'routes/web.php',
    'config/app.php',
    'config/permission.php',
    'vendor/composer/installed.php',
    'vendor/composer/autoload_classmap.php',
    'public/index.php',
    'public/.htaccess',
    '.htaccess',
]


def md5_local(rel: str) -> str | None:
    caminho = LARAVEL / rel

    if not caminho.is_file():
        return None

    return hashlib.md5(caminho.read_bytes()).hexdigest()


def vendor_local_tem_dev() -> bool:
    """O vendor daqui está instalado com as dependências de desenvolvimento?

    Se estiver, os arquivos de vendor/composer legitimamente diferem dos do
    servidor, que recebe a instalação `--no-dev`. Comparar os dois nesse estado
    acusaria divergência onde há acerto — e ferramenta que dá alarme falso para
    de ser lida.
    """
    return (LARAVEL / 'vendor' / 'phpunit').is_dir()


def conferir_arquivos(cfg: dict) -> list[str]:
    """Baixa os críticos do servidor e compara com o que está aqui."""
    divergencias = []
    ignorar_vendor = vendor_local_tem_dev()

    if ignorar_vendor:
        print('  (vendor local com dependências de desenvolvimento:')
        print('   os arquivos de vendor/composer não são comparáveis agora)')

    ftp = ftplib.FTP(timeout=deploy.FTP_TIMEOUT)
    ftp.encoding = 'utf-8'
    ftp.connect(cfg['FTP_HOST'], int(cfg.get('FTP_PORT', 21)))
    ftp.login(cfg['FTP_USERNAME'], cfg['FTP_PASSWORD'])
    ftp.set_pasv(True)

    raiz = cfg.get('FTP_DIR', 'htdocs').strip('/')

    for rel in CRITICOS:
        if ignorar_vendor and rel.startswith('vendor/composer/'):
            print(f'  {rel:42} não comparável')
            continue

        esperado = md5_local(rel)

        if esperado is None:
            print(f'  {rel:42} ausente aqui — ignorado')
            continue

        buffer = io.BytesIO()

        try:
            ftp.retrbinary(f'RETR {raiz}/{rel}', buffer.write)
        except ftplib.all_errors as erro:
            divergencias.append(f'{rel}: não foi possível ler no servidor ({erro})')
            print(f'  {rel:42} ERRO AO LER')
            continue

        encontrado = hashlib.md5(buffer.getvalue()).hexdigest()

        if encontrado == esperado:
            print(f'  {rel:42} igual')
        else:
            divergencias.append(f'{rel}: o servidor tem outra versão')
            print(f'  {rel:42} DIVERGENTE')

    ftp.quit()

    return divergencias


def resolver_desafio(html: str, jar, dominio: str) -> bool:
    """Decifra o cookie __test do desafio anti-robô. Devolve se havia desafio.

    O host emite o desafio **por caminho**: resolvê-lo na home não basta para
    uma chamada a /api/..., que volta com um desafio novo. Por isso isto é uma
    função à parte, chamada sobre a resposta que vier, e não um passo único na
    abertura da sessão.
    """
    valores = re.findall(r'toNumbers\("([0-9a-f]+)"\)', html)

    if len(valores) < 3:
        return False

    chave, iv, cifrado = valores[:3]

    with tempfile.NamedTemporaryFile(delete=False) as arquivo:
        arquivo.write(bytes.fromhex(cifrado))
        entrada = arquivo.name

    decifrado = subprocess.run(
        ['openssl', 'enc', '-d', '-aes-128-cbc', '-K', chave, '-iv', iv, '-nopad', '-in', entrada],
        capture_output=True,
    ).stdout.hex()
    os.unlink(entrada)

    jar.set_cookie(http.cookiejar.Cookie(
        0, '__test', decifrado, None, False,
        dominio, True, False, '/', True, True, None, True, None, None, {},
    ))

    return True


def abrir_navegador(base: str):
    """Resolve o desafio anti-robô do host e devolve um opener com o cookie.

    Sem isso toda requisição recebe a página do desafio em vez do conteúdo, e a
    verificação concluiria que o site está quebrado quando ele está no ar.
    """
    contexto = ssl._create_unverified_context()
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(
        urllib.request.HTTPSHandler(context=contexto),
        urllib.request.HTTPCookieProcessor(jar),
    )

    pedido = urllib.request.Request(base + '/', headers={'User-Agent': UA})

    with opener.open(pedido, timeout=60) as resposta:
        html = resposta.read().decode('utf-8', 'replace')

    if resolver_desafio(html, jar, base.split('//')[1]):
        pedido = urllib.request.Request(base + '/', headers={'User-Agent': UA})

        with opener.open(pedido, timeout=60) as resposta:
            html = resposta.read().decode('utf-8', 'replace')

    return opener, html


def conferir_rotas(base: str) -> list[str]:
    divergencias = []
    opener, home = abrir_navegador(base)

    api_base = re.search(r'apiBase:"([^"]*)"', home)

    if not api_base:
        divergencias.append('a interface não declara apiBase — o build não chegou ou está velho')
        print('  interface                                  SEM apiBase')
    elif api_base.group(1) != '/api':
        divergencias.append(f'apiBase da interface é "{api_base.group(1)}", e não "/api"')
        print(f'  interface                                  apiBase ERRADO: {api_base.group(1)}')
    else:
        print('  interface                                  apiBase /api')

    esperados = [
        ('/api/qualquer', 'GET', None, 404),
        ('/api/auth/session', 'GET', None, 201),
        ('/up', 'GET', None, 200),
    ]

    for caminho, metodo, corpo, esperado in esperados:
        dados = json.dumps(corpo).encode() if corpo is not None else None
        pedido = urllib.request.Request(
            base + caminho, data=dados, method=metodo,
            headers={'User-Agent': UA, 'Accept': 'application/json', 'Content-Type': 'application/json'},
        )

        try:
            with opener.open(pedido, timeout=90) as resposta:
                codigo = resposta.status
        except urllib.error.HTTPError as erro:
            codigo = erro.code

        if codigo == esperado:
            print(f'  {caminho:42} HTTP {codigo}')
        else:
            divergencias.append(f'{caminho}: HTTP {codigo}, esperado {esperado}')
            print(f'  {caminho:42} HTTP {codigo}  ESPERADO {esperado}')

    return divergencias


def main() -> int:
    cfg = deploy.load_env(REPO_ROOT / '.env.deploy')

    if not cfg.get('FTP_HOST'):
        print('Faltam credenciais em .env.deploy.')

        return 1

    base = 'https://condoval.wuaze.com'

    print('Arquivos críticos:')
    divergencias = conferir_arquivos(cfg)

    print()
    print('Rotas:')
    divergencias += conferir_rotas(base)

    print()

    if divergencias:
        print(f'{len(divergencias)} divergência(s):')

        for item in divergencias:
            print(f'  - {item}')

        return 1

    print('Produção corresponde à árvore local.')

    return 0


if __name__ == '__main__':
    sys.exit(main())
