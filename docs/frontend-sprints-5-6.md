# Frontend das Sprints 5 e 6 — revisão de integração

Revisão em 26/11/2026, `main` no merge `9f4b1f1` do PR #52. Fontes: `git diff 9f4b1f1^1 9f4b1f1`, `laravel/routes/api.php`, Actions, FormRequests, Models, Enums e testes de feature do PR. Esta matriz complementa `rastreabilidade-backend.md`.

## Diagnóstico inicial

`app.vue` bloqueava Reservas, Comunicados e Relatórios em API. O painel calculava indicadores carregando entidades inteiras. As reservas usavam ocupação bruta e refaziam a disponibilidade no navegador. As listagens presumiam arrays completos e IDs de texto. Comunicados enviavam autor e data do navegador e não ofereciam rascunho nem publicação posterior. Relatórios e auditoria somavam apenas a página local. Os tipos de ocorrência não expunham `kind`.

## Matriz de rastreabilidade

"Integrada por contrato" significa chamada e mapeamento da rota do PR #52, com typecheck, testes de unidade e build. **Não significa teste autenticado contra Laravel**, indisponível neste ambiente. Por isso os fluxos que dependem de escrita e autorização estão classificados como parciais.

| Requisito / Caso de uso | Endpoint | Permissão | Componente frontend | Situação | Ação necessária |
| --- | --- | --- | --- | --- | --- |
| Painel inicial | `GET /dashboard` | sessão ativa | `app.vue`, `DashboardStat.vue` | Implementada parcialmente | Validar API real e permissões individuais. |
| UC13 gerir áreas | `GET/POST/PATCH/DELETE /common-areas` | `manage-reservations` | `CommonAreaAdminPage.vue` | Implementada parcialmente | Testar escrita e aviso de regras futuras no Laravel. |
| UC14 disponibilidade | `GET /common-areas/{id}/availability` | `view-common-areas`, `request-reservation` ou `manage-reservations` | `ReservationPage.vue` | Implementada parcialmente | Testar calendário com backend e fuso horário real. |
| UC14 ocupação anonimizada | `GET /common-areas/{id}/occupancy` | mesmos casos acima | repositório de reservas | Implementada parcialmente | Executar teste HTTP autenticado; calendário usa disponibilidade. |
| UC15 solicitar reserva | `POST /reservations` | `request-reservation` | `ReservationPage.vue` | Implementada parcialmente | Testar horário, diária, `24:00` e conflito. |
| UC17 minhas reservas | `GET /reservations/me`, `GET /reservations/{id}` | `view-own-reservations` | `ReservationPage.vue` | Implementada parcialmente | Testar filtros e paginação. |
| UC19 gerir reservas | `GET /reservations` | `manage-reservations` ou `approve-or-reject-reservation` | `ReservationPage.vue` | Implementada parcialmente | Validar filtros e identidade do morador. |
| UC16 análise | `PATCH /reservations/{id}/status` | `approve-or-reject-reservation` | modal em `app.vue` | Implementada parcialmente | Testar concorrência e motivo de reprovação. |
| UC18 cancelar | `POST /reservations/{id}/cancel` | `cancel-own-reservation` ou `manage-reservations` | `ReservationPage.vue`, `app.vue` | Implementada parcialmente | Testar liberação imediata. |
| UC4 gerir comunicados | `POST/PATCH/DELETE /notices`, `POST /notices/{id}/publish` | `publish-notices` | `NoticePage.vue` | Implementada parcialmente | Testar transições e paginação. |
| UC5 mural | `GET /notices`, `GET /notices/{id}` | `view-notices` ou `publish-notices` | `NoticePage.vue` | Implementada parcialmente | Validar ocultação de rascunhos. |
| UC7 denúncias | `POST /occurrences`, `GET /occurrences`, CRUD `/occurrence-types` | `create-occurrence`, `manage-occurrence-types` e casos de consulta | `OccurrenceTypeAdminPage.vue`, `app.vue` | Implementada parcialmente | Testar abertura e filtros por natureza. |
| UC20 relatório de ocorrências | `GET /reports/occurrences` | `generate-reports` | `OperationalReportPage.vue` | Implementada parcialmente | Testar agregações, filtros, paginação e exportação. |
| UC20 relatório de reservas | `GET /reports/reservations` | `generate-reports` | `OperationalReportPage.vue` | Implementada parcialmente | Testar áreas zeradas e exportação. |
| UC-T03 auditoria | `GET /audit-logs` | `view-audit-reports` | `AuditServerPage.vue` | Implementada parcialmente | Testar filtros, dados anteriores/posteriores e exportação. |
| Permissões individuais | sessão, permissões efetivas | cada caso de uso | navegação e ações em `app.vue` | Incompatível com o contrato atual | Expor permissões efetivas na sessão ou em endpoint próprio. |

## Contratos e decisões

- Fluxo novo: componente → composable → service → contrato de repositório → implementação API. O modo Mock preserva as telas anteriores e recebeu equivalentes para reservas, áreas, disponibilidade, comunicados e `kind`.
- `MessageService::success(..., true)` retorna `data`, `total` e `amount`. Relatórios e auditoria retornam agregações e uma lista paginada dentro de `data`.
- A agenda do morador usa `/availability`, que já aplica RN01/RN02 e não inclui dados pessoais. O frontend não usa a listagem administrativa para calcular o calendário.
- Autor, publicação e identidade do solicitante vêm da sessão no servidor. Reserva envia `common_area_id`, `date` e horários opcionais; comunicado envia `title`, `content`, `status`.
- `export=true` consulta o período filtrado inteiro. O limite atual é de 5.000 registros por relatório. Se excedido, a mensagem orienta reduzir o período.
- Resumos operacionais se referem ao período global. Filtros adicionais afetam o detalhamento; a tela informa essa regra.
- 401 limpa a sessão visual; 403, 404, 409, 422, 429 e 500 recebem mensagens adequadas. Conflito de reserva recarrega a disponibilidade.

## Limitação de contrato do backend: permissões efetivas

**Endpoint:** `GET /auth/session` e `POST /auth/login`.

**Esperado:** retornar os casos de uso efetivos da pessoa, somando concessões individuais, para a navegação e os controles acompanharem `use.case`.

**Observado na leitura do código:** `AuthController` devolve `User` serializado; `User` não possui atributo serializado de permissões. O frontend só pode preencher `permissions` quando o servidor as enviar. A concessão individual em `POST /users/{id}/permissions` muda a autorização das rotas, mas não entrega ao cliente a lista atualizada. Um morador com `manage-reservations` concedido pode passar pelo middleware e ainda não ver o menu após login. O backend continua protegendo as rotas.

**Reprodução:** conceder `manage-reservations` a um morador, autenticar-se como ele, consultar `GET /auth/session` e verificar a ausência de `permissions`; comparar com `GET /dashboard`, cuja seção `reservations` aparece. Não foi executado aqui por falta de Laravel e credenciais locais. Nenhum mock mascara a diferença.

## Validação e reprodução

Em `frontend/`: `npm ci`, `npm run typecheck`, `npm test`, `npm run build`. Nesta revisão: typecheck passou; Vitest passou com **17 arquivos e 89 testes**; build Nuxt passou. Não há script/configuração ESLint ou Playwright no projeto; lint e E2E não foram executados. O primeiro build e o primeiro Vitest falharam no sandbox com `spawn EPERM`; ambos passaram fora dele. O build avisou sobre chunks acima de 500 kB.

O ambiente não tem PHP, Composer nem Docker. Não foi possível iniciar Laravel, rodar PHPUnit ou obter evidência de integração HTTP autenticada. Vitest exercita mappers e contratos com entradas de API, mas não substitui essa validação. Para fechar o aceite, iniciar Laravel conforme `laravel/README.md`, configurar `NUXT_PUBLIC_DATA_SOURCE=api` e `NUXT_PUBLIC_API_BASE`, autenticar diferentes perfis e concessões individuais, repetir os fluxos da matriz e executar `php artisan test` em ambiente PHP. O backend não foi alterado.

O pedido menciona Nuxt 3, mas o `frontend/package.json` recebido já fixa `nuxt: ^4.0.0` e o build resolve Nuxt 4.4.5. Esta entrega preservou a versão existente para não introduzir uma migração de framework fora do escopo.
