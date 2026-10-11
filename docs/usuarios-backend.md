# Usuários disponíveis no backend

Este documento descreve as contas criadas por `php artisan db:seed` a partir
dos seeders atuais do Laravel.

A fonte é `database/seeders/json/users.json`. O `DemoSeedTest` confere que toda
conta semeada aparece aqui — uma conta acrescentada ao JSON e esquecida nesta
tabela quebra a suíte, em vez de aparecer só na hora da demonstração.

## Contas de demonstração

| Perfil | Nome | E-mail | Unidade atual | Situação | Observação |
| --- | --- | --- | --- | --- | --- |
| Administrador | Administrador CondoVale | `admin@gmail.com` | — | Ativo | Conta principal da administração |
| Síndico | Síndico CondoVale | `syndic@gmail.com` | — | Ativo | Possui concessão individual para visualizar auditoria |
| Funcionário | Funcionário CondoVale | `employee@gmail.com` | — | Ativo | Possui concessão individual para visualizar comunicados |
| Funcionário | Pedro Manutenção | `employee2@gmail.com` | — | Ativo | Segundo funcionário para demonstrar atribuições distintas |
| Morador | Morador CondoVale | `resident@gmail.com` | A-101 | Ativo | Proprietário com vínculo vigente |
| Morador | Bruno Inquilino | `resident2@gmail.com` | A-102 | Ativo | Inquilino com vínculo vigente e histórico na A-202 |
| Morador | Carla Sem Unidade | `resident3@gmail.com` | — | Ativo | Demonstra a restrição de operações que exigem unidade |
| Morador | Joana Desligada | `inactive@gmail.com` | — | Inativo | O login deve ser recusado; possui histórico na A-201 |
| Morador | Marcos Primeiro Acesso | `firstaccess@gmail.com` | B-101 | Ativo | Deve trocar a senha antes de acessar as demais funções |

Senha de todas as contas semeadas: `123456`.

Estas credenciais são exclusivamente para desenvolvimento, testes e
demonstração. Não as use em produção; substitua as senhas antes de
disponibilizar o sistema.

A senha tem seis caracteres, abaixo do mínimo de oito que o
`ChangePasswordRequest` exige: o seeder grava o hash direto e não passa pela
validação. É mais uma razão para esta carga não conviver com dado real.

## Capacidades por perfil

As permissões são materializadas pelo `RolePermissionSeeder` a partir da matriz
de `App\Enums\UserRole`, que é a fonte única — o `PermissionJsonMatrixTest`
confere o JSON contra ela.

| Perfil | Capacidades |
| --- | --- |
| Morador | Atualizar o próprio perfil, visualizar comunicados e áreas comuns, abrir e acompanhar ocorrências próprias, solicitar/visualizar/cancelar as próprias reservas |
| Funcionário | Atualizar o próprio perfil, visualizar ocorrências atribuídas, atualizar o andamento e finalizar ocorrências |
| Síndico | Atualizar o próprio perfil, gerenciar unidades, publicar comunicados e gerar relatórios |
| Administrador | Atualizar o próprio perfil, gerenciar unidades e moradores, vincular moradores a unidades, analisar e atribuir ocorrências, publicar comunicados, gerenciar e aprovar/rejeitar reservas, gerar relatórios e visualizar relatórios de auditoria |

Todos os perfis podem fazer login e editar o próprio cadastro. O síndico
gerencia unidades pela RN03 — "alterações estruturais de unidades restritas ao
Síndico/Administrador" —, mas **não** gerencia pessoas: o cadastro de usuários
continua só do administrador, por `manage-residents`.

A autorização efetiva também considera concessões individuais gravadas pelo
Spatie a partir de `model_has_permissions.json`; portanto, esta tabela
representa as permissões padrão do papel, não eventuais alterações
administrativas. Na carga atual, `syndic@gmail.com` recebe `view-audit-reports`
e `employee@gmail.com` recebe `view-notices` diretamente.

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
