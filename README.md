# condovale

## Como executar

Backend (Laravel):

```bash
cd laravel && composer setup && php artisan serve
```

Frontend (Nuxt):

```bash
cd frontend && npm install && npm run dev
```

Para os detalhes de configuração, consulte [`laravel/README.md`](laravel/README.md)
e [`frontend/README.md`](frontend/README.md).

Produção: [https://condoval.wuaze.com](https://condoval.wuaze.com)

## Documentação

- [Usuários disponíveis no backend](docs/usuarios-backend.md)
- [Plano de usabilidade e acessibilidade](docs/plano-usabilidade-acessibilidade.md)

## Testes de carga

[![Testes de carga](https://github.com/Erick-Mafra-Edu/condovale/actions/workflows/load-tests.yml/badge.svg)](https://github.com/Erick-Mafra-Edu/condovale/actions/workflows/load-tests.yml)

O baseline inicial usa Docker Compose, o seed do Laravel e k6 para medir sessão, usuários, unidades, ocupações, ocorrências e tipos de ocorrência. A execução é manual ou opt-in pelo label `run-load-tests`; no CI econômico, as métricas ficam no resumo da execução e nos artefatos do workflow, sem commits automáticos no README.

Consulte o [plano de testes de carga com Docker e CI](docs/plano-testes-carga-docker-ci.md) e o [guia de execução local](load-tests/README.md). O relatório agregado inclui tempo médio, p95, p99, throughput, erros e checks quando a execução conclui.
