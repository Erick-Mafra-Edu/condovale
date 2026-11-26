# Arquitetura do benchmark

## Aplicação e contratos

O backend é Laravel 11 em `laravel/`, com sessão por cookie HttpOnly, autorização via papéis Spatie e MySQL na implantação. As configurações padrão de cache e sessão são `database`; o smoke anterior substitui ambas por `file`, usa SQLite e `php artisan serve`. O frontend Nuxt usa as rotas de `laravel/routes/api.php`, com login `POST /api/auth/login`, sessão `GET /api/auth/session`, listagens paginadas e operações de reservas, ocorrências, unidades, usuários, comunicados e áreas comuns. O middleware de login limita por conta; cada VU precisa de uma conta independente. `MessageService::success()` devolve 201 também nos GETs; os cenários aceitam esse contrato atual.

| Operação | Endpoint | Perfil | Benchmark |
| --- | --- | --- | --- |
| Login/sessão | `POST /api/auth/login`, `GET /api/auth/session` | todos | smoke/mixed |
| Usuários/unidades/vínculos | `GET /api/users`, `/api/units`, `/api/unit-occupancies` | admin | smoke legado |
| Ocorrências | `GET/POST /api/occurrences` | resident/admin | smoke/mixed |
| Reservas | `GET /api/reservations/me` | resident | smoke/mixed |
| Comunicados/áreas | `GET /api/notices`, `/api/common-areas` | resident | mixed |
| Perfil | `GET/PATCH /api/users/me` | todos | mixed |

## Ambiente proposto

`docker-compose.infra.yml` executa Apache com mod_php 8.3, OPcache e Laravel em `APP_ENV=production`, MySQL 8.4, k6 0.54.0 e um coletor no host. Apache e MySQL têm limites explícitos de CPU/RAM; o k6 não compartilha o contêiner do sistema medido. A preparação aplica migrações e dados sintéticos antes da janela medida. O volume MySQL é descartado ao fim da execução. Sessão e cache usam MySQL, de modo que a carga de autenticação também afeta o banco. Logs de Apache, Laravel, MySQL e k6 são preservados na pasta de resultados.

## Diferenças que influenciam os números

O smoke anterior executa `artisan serve` com SQLite e duas sessões compartilhadas. Seu throughput curto verifica saúde funcional, sem representar Apache, MySQL, contenção no banco ou consumo de recursos. A execução anterior de CI (74,94 req/s, 2 VUs, 10 s) não estabelece capacidade de produção. O benchmark novo usa dados maiores, sessões por VU e taxa de chegada controlada. O coletor usa `docker stats` a cada 2 s; picos mais curtos podem escapar. O host, Docker Desktop/VM, limites de CPU/RAM, cache, aquecimento e I/O afetam os resultados. Não compare hosts distintos como equivalentes.

## Hipóteses e limites

- Perfis de 100/1.200/5.000 usuários e 500/10.000/50.000 reservas são hipóteses experimentais.
- Os registros históricos sintéticos são gravados diretamente após a aplicação de migrações, sem executar as ações HTTP; isso mede consultas em volume, não o custo de criá-los.
- Os resultados de `mixed` incluem autenticação, leituras e escritas leves; as contas e o banco são descartados ao fim.
- A taxa de chegada do k6 é em iterações/s. Uma iteração pode emitir mais de uma requisição HTTP; o relatório registra as duas taxas separadamente. Usa p95 < 800 ms, falhas < 1%, zero iterações descartadas e pelo menos 95% da taxa de iterações solicitada para classificar um patamar. Limite sustentável é uma observação local, não recomendação automática de produção.
