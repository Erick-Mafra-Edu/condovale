"""Mostra o andamento da publicação em curso.

Serve para acompanhar um envio que está rodando em segundo plano: em vez de
depender da saída do processo, lê o mesmo arquivo de estado que o publicador
atualiza a cada lote enviado.

    python tools/deploy-status.py
"""

from __future__ import annotations

import importlib.util
import json
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent

spec = importlib.util.spec_from_file_location("deploy", REPO_ROOT / "tools" / "deploy-infinityfree.py")
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)

if deploy.PROGRESS_FILE.exists():
    print(deploy.PROGRESS_FILE.read_text(encoding="utf-8").strip())

if not deploy.STATE_FILE.exists():
    print("Nenhuma publicação registrada ainda.")
    raise SystemExit(0)

enviados = len(json.loads(deploy.STATE_FILE.read_text(encoding="utf-8")))
total = len(deploy.collect_local())

print(f"{enviados} de {total} arquivos publicados ({enviados * 100 / total:.1f}%)")

if enviados < total:
    print(f"faltam {total - enviados}")
else:
    print("tudo publicado")
