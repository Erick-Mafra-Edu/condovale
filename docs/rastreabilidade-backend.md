# Rastreabilidade do backend — CondoVale

Estado em 24/11/2026, branch `desenv-laravel`.

Este documento liga cada item da especificação formal ("Engenharia de
Software II", versão de 11/09/2026) ao que o backend Laravel entrega: a rota,
a regra (Action) e o teste que prova. No fim estão as decisões que se afastam
do documento formal, que precisam entrar nele.

Os caminhos são relativos a `laravel/`. A autorização é sempre o caso de uso
exigido pela rota (`use.case:<caso>`, matriz em `app/Enums/UserRole.php`, igual
à do frontend em `frontend/app/domain/permissions.ts`). A suíte tem 618 testes
(`php artisan test`); os de disputa entre processos só rodam contra MySQL.

## 1. Requisitos funcionais

| RF | Casos de uso | Rotas principais | Regra | Testes |
| --- | --- | --- | --- | --- |
| RF01 — administrador cadastra, edita e inativa usuários | UC1, UC3 | `/users`, `/users/{id}/deactivate`, `/units`, `/unit-occupancies`, `/users/{id}/permissions`, `/users/me` | `CreateUserAction`, `UpdateUserAction`, `DeactivateUserAction`, `LinkResidentToUnitAction`, `GrantPermissionAction`, `UpdateOwnProfileAction` | `UserCrudTest`, `UnitCrudTest`, `UnitLinkTest`, `UserPermissionCrudTest`, `OwnProfileTest`, `TemporaryPasswordTest` |
| RF02 — login com e-mail e senha | UC2 | `POST /auth/login`, `GET /auth/session`, `POST /auth/logout` | `AuthenticateUserAction` | `AuthenticationTest`, `PasswordHashingTest` |
| RF03 — morador reserva área comum por data e horário | UC13 a UC19 | `/common-areas`, `/common-areas/{id}/availability`, `/reservations`, `/reservations/me`, `/reservations/{id}/status`, `/reservations/{id}/cancel` | `CreateReservationAction`, `CheckReservationConflictAction`, `CheckReservationRulesAction`, `ReviewReservationAction`, `CancelReservationAction` | `ReservationCreationTest`, `ReservationConflictTest`, `ReservationRulesTest`, `ReservationCancellationTest`, `ReservationManagementTest`, `CommonAreaCrudTest`, `CommonAreaHoursTest`, `CommonAreaAvailabilityTest`, `ReservationRaceTest` |
| RF04 — morador abre chamado com descrição textual | UC6 a UC12 | `/occurrences`, `/occurrences/{id}/status`, `/assign`, `/finish`, `/history`, `/attachments`, `/occurrence-types` | `CreateOccurrenceAction`, `AssignOccurrenceAction`, `UpdateOccurrenceStatusAction`, `UpdateOccurrenceAttendanceAction`, `FinishOccurrenceAction` | `OccurrenceCreationTest`, `OccurrenceComplaintTest`, `OccurrenceAssignTest`, `OccurrenceStatusTest`, `OccurrenceAttendanceTest`, `OccurrenceFinishTest`, `OccurrenceStateMachineTest`, `OccurrenceQueryTest`, `OccurrenceRaceTest` |
| RF05 — síndico/administrador publica comunicados | UC4, UC5 | `/notices`, `PATCH /notices/{id}`, `/notices/{id}/publish` | `CreateNoticeAction`, `UpdateNoticeAction`, `PublishNoticeAction`, `DeleteNoticeAction` | `NoticeTest`, `NoticeEditTest` |
| RF06 — síndico/administrador tira relatórios de reservas e manutenções | UC20 | `GET /reports/occurrences`, `GET /reports/reservations` | `BuildOccurrenceReportAction`, `BuildReservationReportAction`, `PaginateReportAction` | `OccurrenceReportTest`, `ReservationReportTest` e os dois `*ReportPerformanceTest` |

## 2. Casos de uso

| Caso de uso | Rota | Caso de uso de permissão | Situação |
| --- | --- | --- | --- |
| UC1 Gerenciar cadastros | `/users`, `/units`, `/unit-occupancies`, `/users/{id}/permissions` | `manage-residents`, `manage-units`, `link-residents-to-units` | entregue |
| UC2 Realizar login | `/auth/login`, `/auth/session`, `/auth/logout` | — | entregue |
| UC3 Atualizar meu cadastro | `GET/PATCH /users/me`, `PATCH /users/me/password` | `update-own-profile` | entregue |
| UC4 Publicar comunicado | `POST /notices`, `PATCH /notices/{id}`, `POST /notices/{id}/publish`, `DELETE /notices/{id}` | `publish-notices` | entregue |
| UC5 Visualizar comunicados | `GET /notices`, `GET /notices/{id}` | `view-notices` | entregue |
| UC6 Registrar ocorrência | `POST /occurrences`, `POST /occurrences/{id}/attachments` | `create-occurrence`, `open-occurrence-for-resident` | entregue |
| UC7 Registrar denúncia | `POST /occurrences` com tipo de natureza `complaint`; `GET /occurrences?kind=complaint` | `create-occurrence` | entregue em 24/11 (ver decisão D1) |
| UC8 Encaminhar ocorrência | `PATCH /occurrences/{id}/assign`, `GET /assignable-employees` | `assign-occurrence`, `hand-over-occurrence` | entregue |
| UC9 Consultar ocorrências atribuídas | `GET /occurrences`, `/{id}`, `/{id}/history` | `view-assigned-occurrences` | entregue |
| UC10 Atualizar andamento | `PATCH /occurrences/{id}`, `PATCH /occurrences/{id}/status` | `update-occurrence-progress`, `analyze-occurrences` | entregue |
| UC11 Finalizar atendimento | `POST /occurrences/{id}/finish` | `finish-occurrence`, `close-any-occurrence` | entregue |
| UC12 Acompanhar minhas solicitações | `GET /occurrences`, `/{id}`, `/{id}/history` | `track-own-occurrences` | entregue |
| UC13 Gerenciar espaços | `POST/PATCH/DELETE /common-areas` | `manage-reservations` | entregue (ver D6) |
| UC14 Consultar espaços disponíveis | `GET /common-areas`, `/{id}/availability`, `/{id}/occupancy` | `view-common-areas`, `request-reservation`, `manage-reservations` | entregue |
| UC15 Solicitar reserva | `POST /reservations` | `request-reservation` | entregue |
| UC16 Aprovar ou rejeitar reserva | `PATCH /reservations/{id}/status` | `approve-or-reject-reservation` | entregue em 24/11 |
| UC17 Consultar minhas reservas | `GET /reservations/me`, `GET /reservations/{id}` | `view-own-reservations` | entregue em 24/11 |
| UC18 Cancelar reserva | `POST /reservations/{id}/cancel` | `cancel-own-reservation`, `manage-reservations` | entregue |
| UC19 Gerenciar reservas | `GET /reservations`, `GET /reservations/{id}`, cancelamento pela administração | `manage-reservations`, `approve-or-reject-reservation` | entregue em 24/11 |
| UC20 Gerar relatórios | `/reports/occurrences`, `/reports/reservations` | `generate-reports` | entregue |
| UC-T03 Auditoria (TS03) | `GET /audit-logs` | `view-audit-reports` | entregue |

Rotas fora da especificação: `GET /dashboard` (painel por caso de uso) e as
rotas de implantação `/deploy/*`, protegidas por token.

## 3. Requisitos não funcionais

| RNF | Como o backend atende | Evidência |
| --- | --- | --- |
| RNF01 — listagens abaixo de 2 s sob carga padrão | paginação com teto de 100 linhas, índices nas colunas filtradas, consultas em número fixo por página | `ListingPerformanceTest` (carga padrão: 500 unidades, 1200 usuários, 10 mil reservas) e os demais `*PerformanceTest`, com o orçamento de 2 s como asserção; as listagens ficam abaixo de 50 ms e o pior caso, a exportação de três meses de relatório, perto de 120 ms no SQLite. Teste de carga k6 em `docs/plano-testes-carga-docker-ci.md` |
| RNF02 — senhas só em Argon2id | driver `argon2id` em `config/hashing.php`; a aplicação recusa subir com outro algoritmo | `PasswordHashingTest` |
| RNF03 — interface responsiva | responsabilidade do frontend | — |
| RNF04 — logs de auditoria das operações críticas | toda mutação grava em `logs` pelo `CreateLogAction`, com ação, registro, autor, perfil, hora, IP e o "de → para"; trilha só de inclusão | `AuditLogTest`, `AuditReportPerformanceTest` |

## 4. Regras de negócio

| RN | Onde está a regra | Testes |
| --- | --- | --- |
| RN01 — sem conflito de reserva | `CheckReservationConflictAction`, com trava na área dentro da transação | `ReservationConflictTest`; `ReservationRaceTest` (MySQL: de 8 pedidos simultâneos, 1 grava) |
| RN02 — disponibilidade, horários e regras da área | `CheckReservationRulesAction`, `CommonArea::hoursProblem()` | `ReservationRulesTest`, `ReservationCreationTest`, `CommonAreaHoursTest` |
| RN03 — só síndico/administrador exclui aviso e altera unidade | casos de uso `publish-notices` e `manage-units`, dos dois perfis | `NoticeTest`, `UnitCrudTest` |
| RN04 — só usuário ativo e autorizado acessa | middlewares `auth`, `user.active` (`EnsureUserIsActive`) e `use.case` (`CheckUseCase`) | `AuthenticationTest`, `RolePermissionTest`, `FrontendPermissionParityTest` |
| RN05 — chamado ligado ao morador e com status | `resident_id` imutável no model; status pela máquina de estados (`OccurrenceStatus`) | `OccurrenceCreationTest`, `OccurrenceStateMachineTest` |
| RN06 — só autorizado altera status ou finaliza | `UpdateOccurrenceStatusAction`, `FinishOccurrenceAction`, `CheckOccurrenceAccessAction`; trava no chamado contra pedidos simultâneos | `OccurrenceTransitionGuardTest`, `OccurrenceStatusTest`, `OccurrenceFinishTest`; `OccurrenceRaceTest` (MySQL: de 6 finalizações simultâneas, 1 vale) |
| RN07 — só síndico/administrador publica | caso de uso `publish-notices` | `NoticeTest` |
| RN08 — cancelar libera o período | só reservas pendentes e aprovadas ocupam a agenda | `ReservationCancellationTest`, `ReservationConflictTest` |

## 5. Backlog

| Item | Situação no backend |
| --- | --- |
| US01 login (S2) | entregue |
| US02 unidades (S2) | entregue |
| US03 moradores (S2) | entregue |
| US04 vínculo morador–unidade (S2) | entregue (`unit_occupancies`, com histórico) |
| TS01 Argon2id (S2) | entregue |
| TS02 listagem de moradores abaixo de 2 s (S2) | entregue e medido |
| US05 registrar ocorrência (S3) | entregue, com imagens |
| US06 analisar ocorrências (S3) | entregue |
| US07 encaminhar ocorrência (S3) | entregue |
| US08 publicar avisos (S4) | entregue, com rascunho e edição |
| US09 relatórios (S4) | entregue, com exportação |
| US10 solicitar reserva (S5) | entregue, com aprovação, consulta e cancelamento |
| TS03 auditoria de logs (S6) | entregue, com consulta |
| TS04 responsividade (S6) | frontend |
| US11 MVP publicado (S6) | o deploy publica a `main` no InfinityFree e aplica as migrations; falta o PR da `desenv-laravel` para a `main` |

## 6. Decisões que se afastam do documento formal

Precisam entrar na especificação, no diagrama de classes ou na tabela de
gerenciamento de mudanças.

- **D1 — UC7 Registrar denúncia** (decisão pendente AB-31 da revisão M2).
  Virou natureza do tipo de ocorrência: `occurrence_types.kind`, manutenção ou
  denúncia. A denúncia é um chamado como os outros (RN05, RN06); a
  administração decide quais tipos são denúncia. Não há caso de uso de
  permissão próprio.
- **D2 — Banco de dados.** MySQL em produção (InfinityFree) e SQLite no
  desenvolvimento, no lugar do PostgreSQL dos diagramas de sequência.
- **D3 — Perfis.** O diagrama tem `SINDICO_ADMINISTRADOR`; o sistema separa
  síndico e administrador, com permissão por caso de uso e concessão
  individual (`/users/{id}/permissions`).
- **D4 — Usuário e unidade.** O vínculo é a tabela `unit_occupancies`, com
  início, fim e histórico; `users.unit_id` é só o atalho da ocupação atual.
- **D5 — Ocorrência.** Entidades a mais: `occurrence_types` (com
  `employee_can_close` e `kind`), `occurrence_attachments` e o histórico. A
  máquina de estados tem também CANCELADA e a baixa administrativa; o
  funcionário é obrigatório no encaminhamento; o chamado encerrado não reabre.
- **D6 — Área comum.** `AreaComum.ativa` é `status` (disponível ou
  indisponível); a área tem também capacidade, imagem, duração máxima e
  aprovação obrigatória. `horarioInicio`/`horarioFim` são `opening_time` e
  `closing_time`, no mesmo dia, com `24:00` para meia-noite e no mínimo 30
  minutos abertos. Gerenciar espaços usa `manage-reservations`, porque a
  matriz de casos de uso não tem um caso próprio.
- **D7 — Reserva.** `StatusReserva` tem quatro estados (pendente, aprovada,
  reprovada, cancelada), não dois. A reserva é feita em blocos de 30 minutos,
  com duração máxima (2 h quando a área não informa) ou diária; quem já
  terminou não se cancela.
- **D8 — Comunicado.** `Comunicado.ativo` é `status` rascunho/publicado com
  `published_at`. Há edição, que guarda o texto anterior na auditoria; um
  publicado não volta a rascunho.
- **D9 — Auditoria.** Cada registro guarda ação, registro afetado, autor e o
  perfil dele na época; a consulta é só do administrador (RN16 do frontend).
  Na carga de demonstração, o síndico recebe a auditoria por concessão
  individual, como prevê o contexto de uso ("o síndico acessa remotamente para
  auditorias").
- **D10 — Entradas de escopo.** Imagens no chamado, painel inicial
  (`/dashboard`), exclusão de imagem e o relatório de auditoria não estão nos
  RF.
- **D11 — Numeração.** Os cards do Trello citam "UC03 - Reservar área comum",
  "UC05 - Publicar comunicados" e "UC06 - Emitir relatórios"; na especificação
  são UC13 a UC19, UC4/UC5 e UC20.

## 7. O que depende de ação fora do código

- Fazer o merge da `desenv-laravel` na `main` por pull request. O deploy
  aplica as migrations de auditoria e de natureza dos tipos automaticamente.
- Conferir no phpMyAdmin que `common_areas` e `reservations` estão em InnoDB.
  Sem isso, as travas da RN01 não valem:
  `SELECT TABLE_SCHEMA, TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_NAME IN ('common_areas', 'reservations');`
- Em produção, marcar os tipos de ocorrência que são denúncia
  (`PATCH /occurrence-types/{id}` com `kind: complaint`). A carga de
  demonstração já traz dois.
- Trocar o `DEPLOY_TOKEN` se ele tiver circulado fora dos segredos.
