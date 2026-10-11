# Usuários disponíveis no backend

Este documento descreve as contas criadas por `php artisan db:seed` a partir
dos seeders atuais do Laravel.

A fonte é `database/seeders/json/users.json`. O `DemoSeedTest` confere que toda
conta semeada aparece aqui — uma conta acrescentada ao JSON e esquecida nesta
tabela quebra a suíte, em vez de aparecer só na hora da demonstração.

## Contas de demonstração

Senha de todas: `123456`.

| Perfil | Nome | E-mail | Unidade | Situação | Serve para demonstrar |
| --- | --- | --- | --- | --- | --- |
| Administrador | Administrador CondoVale | `admin@gmail.com` | — | Ativo | o perfil completo |
| Síndico | Síndico CondoVale | `syndic@gmail.com` | — | Ativo | RN03 e uma concessão individual (`view-audit-reports`) |
| Funcionário | Funcionário CondoVale | `employee@gmail.com` | — | Ativo | fila do funcionário e concessão individual (`view-notices`) |
| Morador | Morador CondoVale | `resident@gmail.com` | A-101 | Ativo | morador proprietário, com reservas e ocorrências |
| Morador | Bruno Inquilino | `resident2@gmail.com` | A-102 | Ativo | inquilino, com vínculo anterior encerrado em A-202 |
| Morador | Carla Sem Unidade | `resident3@gmail.com` | — | Ativo | o 403 do middleware `unit.linked` |
| Funcionário | Pedro Manutenção | `employee2@gmail.com` | — | Ativo | segunda pessoa, para atribuir ocorrência |
| Morador | Joana Desligada | `inactive@gmail.com` | — | **Inativo** | RN04: senha certa, acesso recusado |
| Morador | Marcos Primeiro Acesso | `firstaccess@gmail.com` | B-101 | Ativo | troca de senha obrigatória no primeiro acesso |

A senha tem seis caracteres, abaixo do mínimo de oito que o
`ChangePasswordRequest` exige: o seeder grava o hash direto e não passa pela
validação. É carga de demonstração e não deve conviver com dado real.

## Capacidades por perfil

As permissões são materializadas pelo `RolePermissionSeeder` a partir da matriz
de `App\Enums\UserRole`, que é a fonte única — o `PermissionJsonMatrixTest`
confere o JSON contra ela.

| Perfil | Capacidades |
| --- | --- |
| Morador | Atualizar o próprio perfil, visualizar comunicados e áreas comuns, abrir e acompanhar ocorrências próprias, solicitar/visualizar/cancelar as próprias reservas |
| Funcionário | Atualizar o próprio perfil, visualizar ocorrências atribuídas, atualizar o andamento e finalizar ocorrências |
| Síndico | Atualizar o próprio perfil, **gerenciar unidades** (RN03), publicar comunicados e gerar relatórios |
| Administrador | Atualizar o próprio perfil, gerenciar unidades e moradores, vincular moradores a unidades, analisar e atribuir ocorrências, publicar comunicados, gerenciar e aprovar/rejeitar reservas, gerar relatórios e visualizar relatórios de auditoria |

Todos os perfis podem fazer login e editar o próprio cadastro. O síndico passou
a gerenciar unidades pela RN03 — "alterações estruturais de unidades restritas
ao Síndico/Administrador" —, mas **não** gerencia pessoas: o cadastro de
usuários continua só do administrador, por `manage-residents`.

A autorização efetiva também considera concessões individuais gravadas pelo
Spatie a partir de `model_has_permissions.json`; portanto, esta tabela
representa as permissões padrão do papel, não eventuais alterações
administrativas.

## Usuários de factory

`UserFactory` cria usuários fictícios com nome e e-mail aleatórios, perfil
`resident` e situação ativa por padrão. A senha padrão da factory é
`condovale`; os estados `admin`, `syndic`, `employee`, `resident`, `inactive`,
`unverified` e `mustChangePassword` permitem variar esses atributos nos testes.
Essas contas só existem quando um teste ou script executa a factory e não fazem
parte da carga fixa do `DatabaseSeeder`.

Há ainda `UnitFactory`, `UnitOccupancyFactory`, `NoticeFactory`,
`OccurrenceFactory`, `OccurrenceHistoryFactory`, `ReservationFactory` e
`CommonAreaFactory`. Não existe factory de `Log`: aquela tabela é escrita
somente pela `CreateLogAction`, e uma factory ali seria um convite a desviar
dela.
