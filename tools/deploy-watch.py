"""Acompanha a publicação em tempo real, atualizando a mesma linha.

Diferente do deploy-status.py, que dá uma leitura e encerra, este fica aberto e
redesenha o andamento a cada poucos segundos. Serve para deixar numa janela ao
lado enquanto o envio acontece em outra.

    python tools/deploy-watch.py
    python tools/deploy-watch.py --intervalo 10
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent

# Sem isto o console do Windows mostra os acentos trocados.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

spec = importlib.util.spec_from_file_location("deploy", REPO_ROOT / "tools" / "deploy-infinityfree.py")
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)

LARGURA_BARRA = 28

# Acima disto o envio não está apenas entre lotes: alguma coisa prendeu.
SEGUNDOS_PARA_ALERTAR = 180


def enviados() -> int:
    """Lê quantos arquivos já subiram.

    Devolve -1 quando a leitura pega o arquivo no meio de uma escrita; quem
    chama mantém o último valor bom em vez de exibir um número errado.
    """
    if not deploy.STATE_FILE.exists():
        return 0

    try:
        return len(json.loads(deploy.STATE_FILE.read_text(encoding="utf-8")))
    except (ValueError, OSError):
        return -1


def barra(fracao: float) -> str:
    cheio = int(fracao * LARGURA_BARRA)

    return "#" * cheio + "." * (LARGURA_BARRA - cheio)


def main() -> int:
    parser = argparse.ArgumentParser(description="Acompanha a publicação em tempo real.")
    parser.add_argument("--intervalo", type=float, default=5.0, help="segundos entre as leituras")
    args = parser.parse_args()

    print("Contando os arquivos da publicação...")
    total = len(deploy.collect_local())

    inicio = time.monotonic()
    base = max(enviados(), 0)
    ultimo = base
    mudou_em = time.monotonic()

    while True:
        atual = enviados()

        if atual < 0:
            atual = ultimo

        if atual != ultimo:
            mudou_em = time.monotonic()

        ultimo = atual
        fracao = atual / total if total else 0
        decorrido = time.monotonic() - inicio

        # A taxa é medida a partir do momento em que este monitor começou, e não
        # do início do envio: assim a estimativa acompanha o ritmo atual.
        taxa = (atual - base) / decorrido if decorrido > 0 and atual > base else 0
        restante = (total - atual) / taxa / 60 if taxa > 0 else 0

        if taxa > 0:
            ritmo = f"{taxa:.1f} arq/s · faltam ~{restante:.0f} min"
        else:
            # O publicador só grava o estado a cada lote, então o monitor pode
            # levar um minuto até conseguir medir. Mostrar "0.0 arq/s" aqui dá a
            # impressão de que o envio morreu, quando ele está só entre lotes.
            ritmo = "medindo o ritmo"

            if deploy.PROGRESS_FILE.exists():
                try:
                    partes = [p.strip() for p in deploy.PROGRESS_FILE.read_text(encoding="utf-8").split("·")[1:]]

                    if partes:
                        ritmo = " · ".join(partes)
                except OSError:
                    pass

        # O "atualizado há Xs" é o sinal de vida: enquanto ele reinicia, o envio
        # está andando. Se passar do limite, aí sim há o que investigar.
        sem_mudanca = time.monotonic() - mudou_em
        alerta = "  <-- sem avanco, verifique" if sem_mudanca > SEGUNDOS_PARA_ALERTAR else ""

        linha = (
            f"[{barra(fracao)}] {fracao * 100:5.1f}%  {atual}/{total}"
            f" · {ritmo} · atualizado há {sem_mudanca:.0f}s{alerta}"
        )

        sys.stdout.write("\r" + linha + "   ")
        sys.stdout.flush()

        if atual >= total:
            print()
            print("Publicação concluída.")
            return 0

        time.sleep(args.intervalo)


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print()
        print("Monitor encerrado. A publicação continua rodando.")
