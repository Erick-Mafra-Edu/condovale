"""Executa a migração protegida depois de resolver o desafio do InfinityFree."""

from __future__ import annotations

import importlib.util
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('verify_production', ROOT / 'tools' / 'verify-production.py')
verify = importlib.util.module_from_spec(spec)
spec.loader.exec_module(verify)


def main() -> int:
    base = os.environ.get('APP_URL', '').rstrip('/')
    token = os.environ.get('DEPLOY_TOKEN', '')

    if not base.startswith('https://') or len(token) < 32:
        print('APP_URL ou DEPLOY_TOKEN inválido.', file=sys.stderr)
        return 1

    opener, _ = verify.abrir_navegador(base)
    request = urllib.request.Request(
        base + '/api/deploy/migrate',
        data=b'',
        method='POST',
        headers={
            'Accept': 'application/json',
            'User-Agent': verify.UA,
            'X-Deploy-Token': token,
        },
    )

    try:
        with opener.open(request, timeout=300) as response:
            payload = json.load(response)
    except urllib.error.HTTPError as error:
        print(f'Migração recusada pelo servidor (HTTP {error.code}).', file=sys.stderr)
        return 1

    print(payload.get('message', 'Migração concluída.'))
    return 0


if __name__ == '__main__':
    sys.exit(main())
