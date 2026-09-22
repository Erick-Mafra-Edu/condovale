# CondoVale — API Laravel

Backend do gerenciador de condomínio CondoVale. Este repositório contém, neste
momento, a **fundação** da API: o padrão arquitetural montado e configurado para
rodar localmente. Os módulos (usuários, unidades, áreas comuns, reservas,
ocorrências, comunicados, relatórios e auditoria) são entregues por tasks, cada
uma trazendo a sua migration, o seu seeder, o seu controller, as suas rotas e
os seus testes.

A estrutura segue o padrão definido no [CLAUDE.md](../CLAUDE.md) da raiz do
repositório: controllers finos, regra de negócio em **Actions**, resposta única
pelo **MessageService**, auditoria pelo **CreateLogAction** e seeders com **um
JSON por tabela**.

O frontend Nuxt fica em [`../frontend`](../frontend) e tem dono próprio: nada
aqui altera aquele diretório.

## Como rodar localmente

Requisitos: PHP 8.2+ (com `pdo_sqlite`) e Composer. As dependências já estão em
`vendor/`; use `composer install` apenas se apagar essa pasta.

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Atalho equivalente: `composer setup`. O servidor sobe em
`http://127.0.0.1:8000`, a raiz identifica o serviço e `/up` é o health check.
Ainda não há rotas em `/api`: elas entram com os controllers das tasks.

### Banco de dados

O desenvolvimento local usa **SQLite** (`database/database.sqlite`), que não
exige servidor. A arquitetura oficial do projeto é **PostgreSQL**: habilite
`pdo_pgsql` no PHP e troque as variáveis `DB_*` do `.env`, onde o bloco já está
comentado. Só as três migrations do framework existem por enquanto.

## Os dois ambientes

O projeto roda local e no InfinityFree sem editar arquivo nenhum na troca. Quem
decide é a variável `APP_ENV`, e o mecanismo é o do próprio Laravel: quando o
**servidor** define `APP_ENV` antes de a aplicação subir, o framework carrega
`.env.<ambiente>` em vez de `.env`.

| | Local | InfinityFree |
| --- | --- | --- |
| Arquivo carregado | `.env` | `.env.production` |
| `APP_ENV` | `local` (do próprio arquivo) | `production` (do `SetEnv` no `.htaccess`) |
| Banco | SQLite | MySQL do host |
| `APP_URL` | `http://localhost:8000` | `https://condoval.wuaze.com` |
| `APP_DEBUG` | `true` | `false` |
| Cookie de sessão | comum | `secure`, e as URLs são forçadas para HTTPS |
| CORS | `localhost:3000` | domínio de produção |

Nenhum dos dois arquivos é versionado (`.gitignore` cobre todo `.env*`, exceto
o `.env.example`), então o `.env.production` com as credenciais do host precisa
ser enviado manualmente para o servidor.

Para conferir a configuração de produção a partir da sua máquina, sem alterar
nada:

```bash
php artisan --env=production about
```

## Rodar no InfinityFree

O projeto foi importado de lá e continua preparado para voltar: o
[.htaccess](.htaccess) da raiz é o arquivo que só o host usa. Ele define
`APP_ENV=production`, bloqueia o acesso direto a arquivos ocultos e encaminha
tudo para `public/index.php` — necessário porque a conta serve a raiz, e não a
pasta `public/`.

Passos do deploy:

1. Envie o projeto para o `htdocs`, incluindo `vendor/`.
2. Envie o `.env.production` (ele não está no repositório).
3. Garanta que `storage/` e `bootstrap/cache/` sejam graváveis.
4. Crie o schema no banco do host chamando a rota de implantação (abaixo).

Três limitações do plano gratuito que valem saber antes:

- **Não há SSH**, então não existe onde digitar `php artisan migrate`, e o
  acesso remoto ao MySQL é bloqueado. Por isso existe a rota de implantação
  descrita logo abaixo.
- **Não rode `php artisan config:cache`** sem ter como limpar o cache depois;
  sem SSH, uma configuração cacheada errada fica presa no servidor.
- **Argon2id (RNF02) depende do PHP do host.** Confirme com um `phpinfo()` ou
  com `var_dump(defined('PASSWORD_ARGON2ID'))` antes de subir: sem suporte, a
  criação de senha falha. Trate como bloqueio de requisito — não troque o
  driver em silêncio.

Se o host não tiver o `mod_env` habilitado, o `SetEnv` não terá efeito e o
Laravel voltará a ler o `.env`. Nesse caso, renomeie `.env.production` para
`.env` no servidor; o resto continua igual.

> A configuração de produção foi conferida localmente com
> `php artisan --env=production`, mas o deploy em si não foi testado a partir
> desta máquina.

### Criar o schema sem SSH

`POST /api/deploy/migrate` roda `migrate:fresh --seed` pelo próprio HTTP. Ela é
pública por necessidade — quem implanta ainda não tem usuário no banco, porque
o banco é justamente o que está sendo criado —, então a proteção é um segredo
compartilhado, conferido pelo middleware `deploy.token`:

| Situação | Resposta |
|---|---|
| `DEPLOY_ENABLED=false` ou token com menos de 32 caracteres | 404 — a rota nem admite existir |
| Token ausente ou errado | 403 |
| Token correto | 201 com a saída do artisan em `data` |

```bash
curl -X POST https://condoval.wuaze.com/api/deploy/migrate   -H "X-Deploy-Token: $DEPLOY_TOKEN"
```

O token vale no cabeçalho `X-Deploy-Token` ou no corpo, como `token`. A
comparação é feita com `hash_equals`, e o limitador `throttle:deploy` corta em
3 tentativas por minuto com chave fixa — um balde único para o endpoint, já que
não há conta para chavear e o padrão do projeto proíbe limitar por IP.

**A rota derruba todas as tabelas.** Deixe `DEPLOY_ENABLED=false` no
`.env.production` fora da janela de implantação; ligue, rode, desligue. Gere o
token com `php -r "echo bin2hex(random_bytes(32));"`.

Duas consequências que decorrem do que ela faz:

- **Ela não grava log de auditoria.** A tabela `logs` é uma das que o
  `migrate:fresh` derruba, então o registro seria apagado pela própria operação
  que documenta. A evidência é a saída do artisan devolvida na resposta.
- **Pode estourar o tempo de execução do host.** O plano gratuito corta scripts
  longos; a Action pede mais tempo com `set_time_limit()`, mas o host pode
  ignorar. Se a resposta morrer no meio, o schema fica parcial — chame de novo,
  já que `fresh` recomeça do zero.

### Upload de arquivos

O InfinityFree aceita upload por PHP, com um teto rígido de **10 MB por
arquivo** que o plano gratuito não deixa aumentar: `php.ini` próprio é ignorado
nesse ponto. Além do espaço em disco, a conta tem cota de **inodes** (número de
arquivos e pastas), e cada anexo enviado pelos moradores consome uma unidade —
um sistema que acumula comprovantes e fotos de ocorrência bate nessa cota antes
de bater no disco.

Quando os uploads entrarem, o destino é `storage/app` (disco privado), nunca
`public/`. O `.htaccess` da raiz encaminha qualquer URL para `public/`, então o
que está em `storage/app` não é alcançável pela web — e o download precisa
passar por rota autenticada que verifique quem pode baixar aquele arquivo.

## O que a fundação já entrega

| Peça | Papel |
| --- | --- |
| `app/Services/MessageService.php` | envelope único de resposta da API |
| `app/Http/Actions/HandlePaginationAction.php` | contrato genérico de consulta das listagens |
| `app/Http/Actions/CreateLogAction.php` | trilha de auditoria na tabela `logs` (RNF04) |
| `app/Http/Utils/SanitizeUtil.php` | saneamento de todo id e texto vindo da rota |
| `app/Http/Utils/AuthUtil.php` | acessores tipados do guard de sessão |
| `app/Http/Middleware/CheckUseCase.php` | autorização pelo grupo de rota (`use.case`) |
| `app/Http/Middleware/EnsureUserIsActive.php` | RN04 por requisição (`user.active`) |
| `app/Enums/UserRole.php` + `UseCase.php` | matriz de permissões do diagrama de casos de uso |
| `app/Enums/TypeLogEnum.php` | ids fixos dos tipos de log |
| `app/Exceptions/BusinessRuleException.php` | violação de regra com o status HTTP no código |
| `app/Http/Controllers/DeployController.php` | carga inicial do banco sem SSH |
| `app/Models/*` | só os models que as Actions usam, todos com `$hidden` declarado |
| `app/Http/Requests/*` | validação por recurso, com mensagens em português |
| `app/Http/Actions/*` | as regras RN01–RN08, RN14 e RN17 (ver tabela adiante) |
| `database/factories/*` | fábricas dos models existentes, para os testes dos módulos |
| `lang/pt_BR/validation.php` | mensagens de validação em português |
| `config/hashing.php` | Argon2id como driver padrão (RNF02) |
| `config/cors.php` | origens do Nuxt com credenciais |

### Actions por operação de negócio

| Action | Regra |
| --- | --- |
| `CreateReservationAction` | RN01/RN02 — disponibilidade, horário, duração e conflito |
| `CheckReservationConflictAction` | RN01 — sobreposição, incluindo a diária |
| `CancelReservationAction` | RN08 — cancelar libera o período; só a própria reserva |
| `ReviewReservationAction` | UC16 — aprovar/reprovar, revalidando o conflito |
| `ListOccupancyAction` | RN14 — agenda anonimizada |
| `ScopeReservationsAction` | recorte da agenda por identidade |
| `CreateOccurrenceAction` | RN05 — vínculo com o morador da sessão |
| `AssignOccurrenceAction` | RN06 — atribuição a funcionário ativo |
| `UpdateOccurrenceStatusAction` | RN06 — transições permitidas por papel |
| `FinishOccurrenceAction` | UC11 — conclusão pelo responsável |
| `ScopeOccurrencesAction` | RN06 — recorte da fila por papel |
| `CreateOccurrenceHistoryAction` | RN06 — autoria e transição de cada mudança |
| `CreateUserAction` | RF01 — sem senha conhecida por padrão |
| `UpdateOwnProfileAction` | RN17 — apenas nome e e-mail |

As Actions já estão escritas e testáveis, mas dependem das tabelas que as
migrations das tasks vão criar.

## O que cada task de módulo precisa entregar

1. **Migration** da tabela, com as chaves estrangeiras e os índices.
1. **Model**, quando ainda não existir. A fundação só mantém os models que as
   Actions realmente usam — `User`, `CommonArea`, `Reservation`, `Occurrence`,
   `OccurrenceHistory` e `Log`. `Unit`, `Notice` e `TypeLog` saem junto com as
   migrations, com quem cuidar do banco, e com eles voltam as relações
   `User::unit()`, `User::notices()`, `Occurrence::unit()` e `Log::typeLog()`.
   Todo model novo declara `$hidden`.
2. **Seeder + JSON** em `database/seeders/json/<tabela>.json`, mapeando coluna
   por coluna e usando `updateOrCreate` chaveado por campo de negócio;
   registrar o seeder no `DatabaseSeeder`, na posição certa da ordem de
   dependência.
3. **Controller** fino, no formato canônico do CLAUDE.md: valida pelo Form
   Request, chama as Actions, responde pelo `MessageService`, abre transação
   quando há mais de uma escrita e registra `CreateLogAction`.
4. **Rotas** em `routes/api.php`, dentro do grupo cuja autorização corresponde
   ao caso de uso (`use.case:<caso>`).
5. **Testes** cobrindo as regras de negócio do módulo.

> A tabela `type_logs` é dado de referência com ids fixos usados por
> `App\Enums\TypeLogEnum`; enquanto a task dela não existir, nenhuma operação
> consegue gravar auditoria. É o primeiro seeder da fila e precisa rodar também
> em produção: `php artisan db:seed --class=TypeLogSeeder`.

## Contrato de resposta

Toda resposta sai pelo `MessageService`:

```jsonc
// Registro único — HTTP 201, inclusive em GET
{ "status": true, "message": "Reserva 4 encontrada.", "data": { } }

// Listagem paginada — HTTP 201
{ "status": true, "amount": 10, "total": 57, "data": [ ] }

// Operação sem retorno (delete) — HTTP 201
{ "status": true }

// Regra de negócio violada / sem permissão / não encontrado
{ "status": false, "error": "Já existe uma reserva para esta área, data e horário." }

// Exceção capturada pelo controller
{ "status": false, "message": "...", "code": 409, "error": "..." }

// Validação de Form Request — HTTP 422, formato padrão do Laravel
{ "message": "Informe o título do comunicado. (and 1 more error)", "errors": { "title": [ ] } }
```

Dois comportamentos herdados do padrão: **`success()` devolve 201 em todos os
casos** — o cliente trata `status`, não o código HTTP — e **quando não há dados
nem paginação a resposta sai apenas com `{status: true}`**, sem a chave `data`.

Os campos de entrada e de saída são **snake_case**, iguais às colunas
(`common_area_id`, `start_time`, `unit_id`).

### Consulta das listagens

Toda listagem passa pelo `HandlePaginationAction` e aceita o mesmo contrato de
query string — `attributes`, `search`, `filters`, `filtersOr`, `filtersIn`,
`sort` + `direction`, `relations`, `limit` e `page`:

```
/api/reports/occurrences?filters=created_at:>=:2026-09-01;status:=:completed&sort=created_at&direction=desc
/api/logs?filters=type_log_id:=:6&relations=user;typeLog&limit=50
```

Os nomes de coluna vindos do cliente são validados contra o schema real da
tabela, menos os `$hidden` do model. Para exportar tudo o que passou pelos
filtros (RN12), envie um `limit` maior que o `total` devolvido.

## Autorização

O grupo da rota é o modelo de autorização: o middleware `use.case` recebe os
casos de uso que alcançam aquela rota e libera quando o papel do usuário possui
qualquer um deles. `App\Enums\UserRole::useCases()` é a matriz do diagrama de
casos de uso e repete os identificadores de
`frontend/app/domain/permissions.ts` — se as duas divergirem, a interface passa
a oferecer ações que a API recusa, e é isso que
`tests/Unit/PermissionMatrixTest.php` trava.

Posse de registro (a reserva é minha? a ocorrência foi atribuída a mim?) é
regra de negócio e fica nas Actions, nunca no controller.

Este projeto não usa a tabela `pages` do sistema irmão: as permissões do
CondoVale vêm do diagrama de casos de uso, não de um menu configurável.

## Decisões de segurança

- **RNF02** — `config/hashing.php` fixa Argon2id como driver padrão; o hash
  nunca sai da aplicação (`$hidden` no model `User`).
- **Sessão** — cookie `HttpOnly` emitido pelo Laravel, sem token no cliente,
  como especifica `frontend/docs/mock-api-architecture.md`. O JWT descrito no
  CLAUDE.md pertence ao sistema irmão, cujos clientes são outros.
- **CORS** — `CORS_ALLOWED_ORIGINS` lista as origens do Nuxt e
  `supports_credentials` está ligado (curinga `*` é inválido com credenciais).
- **CSRF** — as rotas de API não passam pelo `ValidateCsrfToken`; a proteção
  atual é o cookie `SameSite=Lax` somado à lista de origens. Em produção, sirva
  frontend e API na mesma origem ou passe a exigir `X-XSRF-TOKEN`.
- **RN04** — o middleware `use.case` exige usuário ativo e o `user.active`
  desconecta quem for inativado durante a sessão.
- **Login** — o limitador `throttle:login` permite 5 tentativas por minuto
  **por conta**, nunca por IP: os moradores acessam pela mesma rede e um limite
  por IP bloquearia o prédio inteiro.

## Testes

```bash
php artisan test --compact
php artisan test --compact --filter=PermissionMatrixTest
```

Hoje existe a cobertura da matriz de permissões e dos quatro caminhos da rota
de implantação; os testes de regra de negócio de cada módulo vêm com a task
correspondente.

## O que mudou em relação ao import do InfinityFree

- `composer.json` apontava para `/usr/local/bin/php` e para um `cache-dir`
  absoluto do host; os scripts agora usam `@php` e rodam em qualquer máquina.
- `.env` continha o MySQL do InfinityFree; virou configuração local. As
  credenciais de produção passaram para `.env.production`, ignorado pelo git
  junto com qualquer outro `.env` — só o `.env.example` é versionado.
- `index2.html` e o arquivo-marcador "files for your website..." foram
  removidos, e o `.htaccess` da raiz deixou de redirecionar para
  `condoval.wuaze.com`.

## Pendências conhecidas

- O cancelamento de ocorrência está previsto para o administrador enquanto o
  atendimento não começou; a especificação não define quem pode cancelar.
- Reabertura de ocorrência concluída e reatribuição de atendimento já iniciado
  não são permitidas — ambas dependem de decisão registrada.
- `RNF01` (consultas abaixo de 2 s) ainda não tem carga padrão nem medição.
