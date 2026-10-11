# Usuários disponíveis no backend

Este documento descreve as contas criadas por `php artisan db:seed` a partir
dos seeders atuais do Laravel.

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

Estas credenciais são exclusivamente para desenvolvimento e testes locais.
Não as use em produção; substitua as senhas antes de disponibilizar o sistema.

## Capacidades por perfil

As permissões são materializadas pelo `RolePermissionSeeder` a partir da matriz
de `App\\Enums\\UserRole`:

| Perfil | Capacidades |
| --- | --- |
| Morador | Atualizar o próprio perfil, visualizar comunicados e áreas comuns, abrir e acompanhar ocorrências próprias, solicitar/visualizar/cancelar as próprias reservas |
| Funcionário | Atualizar o próprio perfil, visualizar ocorrências atribuídas, atualizar o andamento e finalizar ocorrências |
| Síndico | Atualizar o próprio perfil, gerenciar unidades, publicar comunicados e gerar relatórios |
| Administrador | Atualizar o próprio perfil, gerenciar unidades e moradores, vincular moradores a unidades, analisar e atribuir ocorrências, publicar comunicados, gerenciar e aprovar/rejeitar reservas, gerar relatórios e visualizar relatórios de auditoria |

Todos os perfis podem fazer login. A autorização efetiva também considera
concessões individuais gravadas pelo Spatie; portanto, esta tabela representa
as permissões padrão do papel, não eventuais alterações administrativas.
Na carga atual, `syndic@gmail.com` recebe `view-audit-reports` e
`employee@gmail.com` recebe `view-notices` diretamente.

## Usuários de factory

`UserFactory` cria usuários fictícios com nome e e-mail aleatórios, perfil
`resident` e situação ativa por padrão. A senha padrão da factory é
`condovale`; os estados `admin`, `syndic`, `employee`, `inactive` e
`unverified` permitem variar esses atributos nos testes. Essas contas só
existem quando um teste ou script executa a factory e não fazem parte da carga
fixa do `DatabaseSeeder`.
