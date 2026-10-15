"""Executa uma tarefa de implantação depois de resolver o desafio do InfinityFree.

    python tools/run-deploy-migration.py              # migrate:fresh --seed
    python tools/run-deploy-migration.py --pending    # só as migrations pendentes, sem apagar nada
    python tools/run-deploy-migration.py --optimize   # config:cache + route:cache
    python tools/run-deploy-migration.py --clear      # apaga os caches de inicialização

O host intercepta a requisição com um desafio em JavaScript e só serve o
conteúdo depois que o cookie `__test` volta certo. **O desafio é reemitido por
caminho**: resolvê-lo na home não basta para o POST, que volta com um desafio
novo. Por isso a chamada é repetida resolvendo o que vier — sem isso a resposta
chega como HTML, o `json.load` estoura e o passo de implantação cai por um
motivo que não aparece no erro.

As credenciais vêm do ambiente, que é como o workflow as passa, e na falta
delas de laravel/.env.production, para a ferramenta servir também à execução
manual.
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ENV_PRODUCAO = ROOT / 'laravel' / '.env.production'

MAX_DESAFIOS = 4

spec = importlib.util.spec_from_file_location('verify_production', ROOT / 'tools' / 'verify-production.py')
verify = importlib.util.module_from_spec(spec)
spec.loader.exec_module(verify)


def do_env(chave: str) -> str:
    """Lê a chave do ambiente e, faltando, de laravel/.env.production."""
    valor = os.environ.get(chave, '')

    if valor or not ENV_PRODUCAO.is_file():
        return valor

    for linha in ENV_PRODUCAO.read_text(encoding='utf-8').splitlines():
        if linha.startswith(chave + '='):
            return linha.split('=', 1)[1].strip().strip('"').strip("'")

    return ''


def chamar(opener, jar, dominio: str, url: str, token: str, corpo: dict) -> tuple[int, str]:
    """Faz o POST, resolvendo quantos desafios o host emitir para o caminho."""
    dados = json.dumps(corpo).encode()

    for tentativa in range(1, MAX_DESAFIOS + 1):
        pedido = urllib.request.Request(
            url,
            data=dados,
            method='POST',
            headers={
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'User-Agent': verify.UA,
                'X-Deploy-Token': token,
            },
        )

        try:
            with opener.open(pedido, timeout=300) as resposta:
                status, texto = resposta.status, resposta.read().decode('utf-8', 'replace')
        except urllib.error.HTTPError as erro:
            status, texto = erro.code, erro.read().decode('utf-8', 'replace')

        if verify.resolver_desafio(texto, jar, dominio):
            print(f'  tentativa {tentativa}: o host devolveu desafio; cookie renovado, repetindo.')
            continue

        return status, texto

    return 0, 'O host continuou devolvendo o desafio depois de várias tentativas.'


def main() -> int:
    parser = argparse.ArgumentParser(description='Tarefas de implantação do CondoVale.')
    grupo = parser.add_mutually_exclusive_group()
    grupo.add_argument('--optimize', action='store_true', help='gera os caches de inicialização')
    grupo.add_argument('--clear', action='store_true', help='apaga os caches de inicialização')
    grupo.add_argument('--pending', action='store_true', help='aplica só as migrations pendentes, sem apagar dados')
    args = parser.parse_args()

    base = do_env('APP_URL').rstrip('/')
    token = do_env('DEPLOY_TOKEN')

    if not base.startswith('https://') or len(token) < 32:
        print('APP_URL ou DEPLOY_TOKEN inválido.', file=sys.stderr)
        return 1

    if args.optimize or args.clear:
        caminho, corpo = '/api/deploy/optimize', {'clear': args.clear}
        rotulo = 'Caches de inicialização apagados.' if args.clear else 'Caches de inicialização gerados.'
    elif args.pending:
        caminho, corpo = '/api/deploy/migrate', {'fresh': False}
        rotulo = 'Migrations pendentes aplicadas.'
    else:
        caminho, corpo = '/api/deploy/migrate', {}
        rotulo = 'Migração concluída.'

    opener, _ = verify.abrir_navegador(base)

    # O jar fica no processador de cookies do opener; é nele que o desafio
    # resolvido a cada tentativa precisa ser gravado.
    jar = next(
        processador.cookiejar
        for processador in opener.handlers
        if hasattr(processador, 'cookiejar')
    )

    status, texto = chamar(opener, jar, base.split('//')[1], base + caminho, token, corpo)

    if status not in (200, 201):
        print(f'Tarefa recusada pelo servidor (HTTP {status}).', file=sys.stderr)
        print(texto[:500], file=sys.stderr)
        return 1

    try:
        payload = json.loads(texto)
    except json.JSONDecodeError:
        print('O servidor respondeu algo que não é JSON:', file=sys.stderr)
        print(texto[:500], file=sys.stderr)
        return 1

    print(payload.get('message', rotulo))

    dados = payload.get('data')

    if isinstance(dados, dict):
        for comando, saida in dados.items():
            print(f'  {comando}: {saida}')

    return 0


if __name__ == '__main__':
    sys.exit(main())
