# Registro de implementação e validação

## Fase 1 — auditoria

Inspecionados `load-tests/`, workflows, rotas, seeders, factories, modelos e configuração Laravel. O smoke anterior usa SQLite, `artisan serve` e sessões compartilhadas. O contrato HTTP atual retorna 201 também em GET. Inventário e limitações: [architecture.md](architecture.md).

## Fases 2 e 3 — infraestrutura e dados

Criados Compose Apache/MySQL, configurações de OPcache, workers e MySQL, healthchecks, limites e script de execução. O comando Artisan gera três perfis sintéticos em banco isolado, repete com segurança a mesma carga, valida contagens e vínculo entre morador, unidade e ocorrência. Decisão: dados históricos são inseridos diretamente para que o custo de preparação não entre na medição.

## Fases 4 a 7 — cenários, observabilidade, relatórios e CI

Criados cenários smoke, baseline, baseline curta, stress, spike, soak e mixed. O k6 usa cookie por VU, mede operações por tags e separa login. O coletor amostra Apache e MySQL, com estatísticas de CPU, RAM, rede, I/O, throttling e status MySQL. O relatório usa apenas arquivos coletados. O workflow Apache/MySQL é manual; o smoke SQLite existente não foi alterado.

## Validação disponível neste host

- `python -m unittest discover -s load-tests/tests -v`: 1 teste aprovado.
- `python -m py_compile load-tests/run.py load-tests/report.py load-tests/compare.py`: aprovado.
- `node --check load-tests/scenarios/infra.js`: aprovado.
- `git diff --check`: aprovado.
- Docker, PHP e Composer não estão no PATH deste host. Build, migrações, execução k6, coleta real e critérios de integração não puderam ser verificados aqui.

## Fase 8 — campanha experimental pendente

A matriz A–D, três repetições por configuração, stress e soak exigem Docker operacional e autorização antes de uma campanha longa de stress. Sem esses dados, não há capacidade observada nem recomendação de infraestrutura defensável. O procedimento e os comandos estão em `load-tests/README.md`.
