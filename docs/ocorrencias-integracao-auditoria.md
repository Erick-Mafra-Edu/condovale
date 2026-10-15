# Auditoria de integração de ocorrências

## Rotas registradas no Laravel

O backend atual registra criação, transição de status, finalização e CRUD de tipos:

| Operação | Endpoint | Situação |
| --- | --- | --- |
| Criar | `POST /occurrences` | Integrada; usa `occurrence_type_id` e aceita `resident_id` somente quando autorizado |
| Atualizar status | `PATCH /occurrences/{id}/status` | Integrada; a Action aplica a máquina de estados e permissões |
| Finalizar | `POST /occurrences/{id}/finish` | Integrada; exige `resolved` e `conclusion` |
| Listar, detalhar, histórico e atribuir | — | Bloqueados: não há rotas HTTP registradas |
| Listar tipos | `GET /occurrence-types` | Integrada; suporta `filters=active:=:1` para tipos ativos |
| Consultar tipo | `GET /occurrence-types/{id}` | Integrada |
| Criar tipo | `POST /occurrence-types` | Integrada para `manage-occurrence-types` |
| Editar tipo | `PATCH /occurrence-types/{id}` | Integrada para `manage-occurrence-types` |
| Excluir/inativar tipo | `DELETE /occurrence-types/{id}` | Integrada; o backend inativa tipos em uso |

## Contratos

`OccurrenceStoreRequest` valida `title`, `description`, `occurrence_type_id` ativo e `resident_id` opcional. A autorização de abrir em nome de outro morador é aplicada pela Action, não pelo frontend. `OccurrenceFinishRequest` exige `resolved` e `conclusion`.

O frontend mantém o modo mock. No modo API, não chama endpoints inexistentes para listagem, detalhe, histórico ou atribuição; essas capacidades permanecem bloqueadas até que o Laravel as exponha.
