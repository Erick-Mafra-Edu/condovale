# Usuários disponíveis no backend

Este documento descreve as contas criadas por `php artisan db:seed` a partir
dos seeders atuais do Laravel.

## Contas de desenvolvimento

| Perfil | Nome | E-mail | Unidade | Situação |
| --- | --- | --- | --- | --- |
| Administrador | Administrador Geral | `admin@condovale.com` | — | Ativo |
| Síndico | Carlos Síndico | `sindico@condovale.com` | — | Ativo |
| Funcionário | João Portaria | `portaria@condovale.com` | — | Ativo |
| Funcionário | Pedro Manutenção | `manutencao@condovale.com` | — | Ativo |
| Morador | Ana Silva | `morador1@condovale.com` | A-101 | Ativo |
| Morador | Bruno Oliveira | `morador2@condovale.com` | A-102 | Ativo |
| Morador | Carla Souza | `morador3@condovale.com` | B-201 | Ativo |

Senha de todas as contas semeadas: `senha123`.

Estas credenciais são exclusivamente para desenvolvimento e testes locais.
Não as use em produção; substitua as senhas antes de disponibilizar o sistema.

## Capacidades por perfil

As permissões são materializadas pelo `RolePermissionSeeder` a partir da matriz
de `App\\Enums\\UserRole`:

| Perfil | Capacidades |
| --- | --- |
| Morador | Atualizar o próprio perfil, visualizar comunicados e áreas comuns, abrir e acompanhar ocorrências próprias, solicitar/visualizar/cancelar as próprias reservas |
| Funcionário | Visualizar ocorrências atribuídas, atualizar o andamento e finalizar ocorrências |
| Síndico | Publicar comunicados e gerar relatórios |
| Administrador | Gerenciar unidades e moradores, vincular moradores a unidades, analisar e atribuir ocorrências, publicar comunicados, gerenciar e aprovar/rejeitar reservas, gerar relatórios e visualizar relatórios de auditoria |

Todos os perfis podem fazer login. A autorização efetiva também considera
concessões individuais gravadas pelo Spatie; portanto, esta tabela representa
as permissões padrão do papel, não eventuais alterações administrativas.

## Usuários de factory

`UserFactory` cria usuários fictícios com nome e e-mail aleatórios, perfil
`resident` e situação ativa por padrão. A senha padrão da factory é
`condovale`; os estados `admin`, `syndic`, `employee`, `inactive` e
`unverified` permitem variar esses atributos nos testes. Essas contas só
existem quando um teste ou script executa a factory e não fazem parte da carga
fixa do `DatabaseSeeder`.
