# Fundação Mock → API

## Fluxo e seleção

`Page/Component → Composable → Service → Repository Contract → MockRepository | ApiRepository`

`app/services/index.ts` concentra a única decisão de seleção. `NUXT_PUBLIC_DATA_SOURCE=mock` mantém dados em memória; `NUXT_PUBLIC_DATA_SOURCE=api` seleciona os ApiRepositories. O padrão continua sendo mock. `NUXT_PUBLIC_API_BASE` configura a base HTTP (padrão `/api`). Services recebem apenas contratos; composables importam `useServices` e objetos de domínio. Não há HTTP, endpoints ou mocks nas camadas superiores.

## Autenticação e responsabilidade de dados

`User` é uma projeção segura para a interface e não possui `senhaHash`, token ou método que faça HTTP. A autenticação é uma operação de aplicação: `useAuth() → AuthService → AuthRepository`. Em modo API, `POST /auth/login`, `GET /auth/session` e `POST /auth/logout` usam `credentials: 'include'`; o backend deve manter a sessão em cookie `HttpOnly` e devolver somente `{ user }`. Credenciais são transitórias e o frontend não usa `localStorage` para tokens.

Validações de input novas usam **Zod**. O schema vive no domínio do caso de uso e o service converte os erros para `AppError`, preservando o formato `{ fields }` que os composables já expõem para a UI.

## Decisões

- **Availability:** `getBlockingReservations(areaId, date)` retorna reservas `pending` ou `approved` de uma área em uma data; não calcula slots. O nome explicita as reservas ocupando a agenda, preservando o domínio simples. Não existe DTO de disponibilidade preventiva.
- **Paginação:** nenhuma tela usa paginação; `list()` retorna a coleção completa em mock e API, no envelope `{ data, message }`. Paginação será acrescentada ao contrato somente quando houver necessidade real.
- **Erros:** `AppError` contém `code`, `message` e `fields?`. Validações dos services e erros do mock usam esse formato; `repositories/api/request.ts` traduz falhas HTTP. Composables mantêm o erro completo, incluindo campos, e não inspecionam Laravel ou fetch. Códigos incluem `VALIDATION_ERROR`, `RESERVATION_CONFLICT`, `NETWORK_ERROR`, `SERVER_ERROR` e `NOT_FOUND`.
- **User ↔ Unit:** `User.unitId` é a única referência persistida. `Unit.residentIds` foi removido porque não havia consumidor ou requisito que exigisse o campo. A lista de moradores de uma unidade pode ser derivada filtrando usuários por `unitId`.
- **DTOs:** os adaptadores API atualmente esperam objetos iguais ao domínio. Se o backend retornar outro formato, a conversão deverá ocorrer dentro do ApiRepository correspondente.

## Operações

| Módulo | Operações |
| --- | --- |
| Reservations | Listagem, consulta individual, criação, cancelamento, status, áreas e reservas ocupando a agenda |
| Occurrences | Listagem, consulta individual, criação, atribuição, status, conclusão e histórico |
| Notices | `list`, `findById`, `create`, `update`, `remove` |
| Users | `list`, `findById`, `create`, `update`, `deactivate` |
| Units | `list`, `findById` |
| Auth | autenticar, restaurar sessão e logout; retorna somente usuário sanitizado |
| Audit | listagem de eventos críticos para relatório administrativo |

Os mocks são mutáveis em memória e devolvem cópias dos objetos. A persistência em banco e autenticação real permanecem fora desta etapa. Os seeds de usuários e unidades mantêm a mesma referência `unit-01`.

## Contrato HTTP provisório

Todos os caminhos, verbos e envelopes abaixo são provisórios, não representam um contrato Laravel validado:

- Coleções: `/api/reservations`, `/api/occurrences`, `/api/notices`, `/api/users`, `/api/units`.
- Auditoria: `GET /api/audit-logs`; o backend deve limitar a consulta a administradores e derivar autoria/data da sessão e do servidor.
- Leitura: `GET` coleção ou `GET /{id}`. Criação: `POST` coleção. Alteração: `PATCH /{id}`.
- Notices: `DELETE /notices/{id}` retorna `{ data: null, message: null }`.
- Users: `POST /users/{id}/deactivate` retorna o usuário com status `inactive`.
- Reservations consulta reservas bloqueantes com `GET /common-areas/{id}/blocking-reservations?date=...`.
- Occurrences usa `PATCH /{id}/assign`, `PATCH /{id}/status`, `POST /{id}/finish` e `GET /{id}/history` dentro de `/occurrences`. `userId` identifica explicitamente o autor dos registros do histórico, provisoriamente até existir autenticação.
- Sucesso retorna `ApiResponse<T>`; listas completas não recebem parâmetros de paginação. Um backend com envelope, paginação ou resposta `204` diferente precisará de adaptação no ApiRepository.
- Erros podem informar `{ code, message, fields }`; o adaptador também aceita `errors` para validação e usa o status HTTP quando não há código explícito.

## Estrutura relevante

```text
app/
  domain/
    app-error.ts
    audit.ts
    common.ts
    notice.ts
    occurrence.ts
    reservation.ts
    user.ts                 # User e Unit
  repositories/
    contracts/
      reservation-repository.ts
      occurrence-repository.ts
      notice-repository.ts
      user-repository.ts
      unit-repository.ts
      audit-repository.ts
    mock/
      mock-reservation-repository.ts
      mock-occurrence-repository.ts
      mock-notice-repository.ts
      mock-user-repository.ts
      mock-unit-repository.ts
      mock-audit-repository.ts
      mock-config.ts
      state.ts
    api/
      api-reservation-repository.ts
      api-occurrence-repository.ts
      api-notice-repository.ts
      api-user-repository.ts
      api-unit-repository.ts
      api-audit-repository.ts
      request.ts
  services/
    index.ts
    reservation-service.ts
    occurrence-service.ts
    notice-service.ts
    user-service.ts
    unit-service.ts
    audit-service.ts
  composables/
    use-reservations.ts
    use-occurrences.ts
    use-notices.ts
    use-users.ts
    use-units.ts
    use-audit-logs.ts
```

## Validação

Execute `npm run typecheck` em `frontend/` (equivalente a `npx nuxt typecheck`). `vue-tsc` e `typescript` são dependências de desenvolvimento; `tsconfig.json` referencia as configurações geradas pelo Nuxt 4.

Resultado em 15/09/2026: `npm run typecheck` concluído com código de saída 0, sem erros TypeScript. Os imports internos usam `~/domain`, `~/repositories` e `~/services`, pois no Nuxt 4 `~` já aponta para `app/`.

A revisão final verifica contrato, implementação mock/API, dependências service/composable e seleção central de cada módulo. A equivalência é contratual nesta etapa: ainda não há backend Laravel integrado para um teste ponta a ponta real.

Uma verificação em memória com HTTP simulado validou Reservations, Notices, Users e Units: métodos equivalentes, listagem, leitura individual, cópias dos dados e `NOT_FOUND`; normalização de HTTP 422/503 e falhas de rede; simulação de `SERVER_ERROR` no mock e conflito de reserva.

A verificação final também aprovou o fluxo de Occurrences: criação, atribuição, mudança de status, conclusão, histórico com autoria, clonagem e `NOT_FOUND`. Os cinco composables foram exercitados através da seleção central em mock e API com fetch simulado, incluindo preservação dos campos de validação HTTP 422. Foram verificados também HTTP 409 normalizado, IDs gerados protegidos contra sobrescrita e tentativa de reativar reserva conflitante mantendo seu status `cancelled`.

| Módulo | Contrato | Mock | API | Service | Composable | Swap centralizado |
| --- | --- | --- | --- | --- | --- | --- |
| Reservations | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Occurrences | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Notices | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Users | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Units | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Audit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

Os services dependem somente dos contratos e do domínio; os composables dependem dos services e do domínio. A seleção central é o único ponto que conhece as duas implementações. A validação não utilizou Laravel real.

## Arquivos criados e modificados

Todos os caminhos abaixo são relativos a `frontend/`.

Criados:

- `app/domain/app-error.ts`.
- `app/repositories/api/request.ts`.
- `app/repositories/contracts/occurrence-repository.ts`, `notice-repository.ts`, `user-repository.ts` e `unit-repository.ts`.
- `app/repositories/mock/mock-occurrence-repository.ts`, `mock-notice-repository.ts`, `mock-user-repository.ts` e `mock-unit-repository.ts`.
- `app/repositories/api/api-occurrence-repository.ts`, `api-notice-repository.ts`, `api-user-repository.ts` e `api-unit-repository.ts`.
- `app/services/occurrence-service.ts`, `notice-service.ts`, `user-service.ts` e `unit-service.ts`.
- `app/composables/use-occurrences.ts`, `use-notices.ts`, `use-users.ts` e `use-units.ts`.
- `tsconfig.json` e `docs/mock-api-architecture.md`.

Modificados:

- `app/domain/common.ts`, `notice.ts`, `occurrence.ts` e `user.ts`.
- `app/repositories/contracts/reservation-repository.ts`.
- `app/repositories/mock/mock-reservation-repository.ts`, `mock-config.ts` e `state.ts`.
- `app/repositories/api/api-reservation-repository.ts`.
- `app/services/reservation-service.ts` e `index.ts`.
- `app/composables/use-reservations.ts`.
- `package.json`, `package-lock.json` e `README.md`.

## Prontidão

A arquitetura está pronta para iniciar a implementação das telas utilizando mocks sem criar dependência relevante da futura API Laravel.
