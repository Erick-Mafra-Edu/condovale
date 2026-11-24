# Auditoria de integração de ocorrências

Atualizado em 24/11/2026. A rastreabilidade completa do backend está em
[rastreabilidade-backend.md](./rastreabilidade-backend.md).

## Rotas registradas no Laravel

| Operação | Endpoint | Situação |
| --- | --- | --- |
| Criar (UC6 e UC7) | `POST /occurrences` | Integrada; usa `occurrence_type_id` e aceita `resident_id` somente quando autorizado |
| Listar | `GET /occurrences` | Integrada; cada perfil vê o seu recorte; `kind=complaint` ou `kind=maintenance` separa denúncias de manutenção |
| Detalhar | `GET /occurrences/{id}` | Integrada; fora do recorte responde 404 |
| Histórico | `GET /occurrences/{id}/history` | Integrada |
| Atualizar status | `PATCH /occurrences/{id}/status` | Integrada; a Action aplica a máquina de estados e permissões |
| Atualizar andamento | `PATCH /occurrences/{id}` | Integrada; status, anotação ou os dois |
| Encaminhar ou repassar | `PATCH /occurrences/{id}/assign` | Integrada; funcionário obrigatório, justificativa no repasse |
| Funcionários para encaminhar | `GET /assignable-employees` | Integrada |
| Moradores para abrir em nome de outro | `GET /occurrence-residents` | Integrada |
| Finalizar | `POST /occurrences/{id}/finish` | Integrada; exige `conclusion`, `resolved` padrão verdadeiro |
| Imagens | `GET/POST /occurrences/{id}/attachments`, `GET/DELETE /occurrences/{id}/attachments/{attachment}` | Integrada; até 5 por chamado |
| Listar tipos | `GET /occurrence-types` | Integrada; suporta `filters=active:=:1` para tipos ativos |
| Consultar tipo | `GET /occurrence-types/{id}` | Integrada |
| Criar tipo | `POST /occurrence-types` | Integrada para `manage-occurrence-types`; `kind` define se é denúncia |
| Editar tipo | `PATCH /occurrence-types/{id}` | Integrada para `manage-occurrence-types` |
| Excluir tipo | `DELETE /occurrence-types/{id}` | Integrada; tipo em uso responde 409 — inative-o pelo `PATCH` com `active: false` |

## Contratos

`OccurrenceStoreRequest` valida `title`, `description`, `occurrence_type_id`
ativo e `resident_id` opcional. A autorização de abrir em nome de outro morador
é aplicada pela Action, não pelo frontend. `OccurrenceFinishRequest` exige
`conclusion`.

Cada ocorrência devolve `occurrence_type` com `id`, `name`, `kind`
(`maintenance` ou `complaint`) e `employee_can_close`. Das pessoas saem só id e
nome.

Toda mudança de chamado relê o chamado com trava dentro da transação: dois
pedidos simultâneos efetivam uma transição só, e o segundo recebe 409.
