# Auditoria de integração de ocorrências

## Resultado

Na branch atual, `laravel/routes/api.php` não registra rotas de ocorrências nem de tipos de ocorrência, e não existe `OccurrenceController`. O Laravel possui apenas a camada de domínio (models, migrations, Form Requests e Actions).

Consequentemente, nenhuma operação de ocorrência pode ser habilitada com segurança em `NUXT_PUBLIC_DATA_SOURCE=api`:

| Operação | Endpoint declarado no frontend | Situação no Laravel |
| --- | --- | --- |
| Listar | `GET /occurrences` | Bloqueada: rota inexistente |
| Detalhar | `GET /occurrences/{id}` | Bloqueada: rota inexistente |
| Criar | `POST /occurrences` | Bloqueada: rota inexistente |
| Atribuir | `PATCH /occurrences/{id}/assign` | Bloqueada: rota inexistente |
| Atualizar status | `PATCH /occurrences/{id}/status` | Bloqueada: rota inexistente |
| Finalizar | `POST /occurrences/{id}/finish` | Bloqueada: rota inexistente |
| Histórico | `GET /occurrences/{id}/history` | Bloqueada: rota inexistente |
| Tipos de ocorrência | `/occurrence-types` | Bloqueada: recurso inexistente |

## Contrato encontrado

`OccurrenceStoreRequest` atualmente valida `title`, `description` e `category`; não valida `occurrence_type_id`. `OccurrenceFinishRequest` aceita `message` opcional; não há campos `resolved` ou `conclusion` nesta branch. A autorização de domínio existe nas Actions e na matriz de `UseCase`, mas não há camada HTTP que a exponha.

O frontend deve manter essas operações em mock até o backend fornecer as rotas e contratos. Não foi criada uma interface administrativa de tipos nem foram inventadas rotas para contornar a ausência do recurso.

