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

### O que vai para o `htdocs`

| Envia | Por quê |
| --- | --- |
| `.htaccess` | roteia para `public/` e identifica o ambiente |
| `.env` | o conteúdo do `.env.production`, com este nome (ver abaixo) |
| `app/`, `config/`, `routes/`, `lang/` | a aplicação |
| `bootstrap/` | incluindo `cache/`, que precisa ser gravável |
| `database/` | migrations, seeders e JSONs — a rota de carga depende deles |
| `public/` | `index.php`, `.htaccess`, `favicon.ico`, `robots.txt` |
| `resources/views/` | as views de erro do Laravel |
| `storage/` | só a estrutura de pastas; os `.gitignore` dela é que criam os diretórios |
| `vendor/` | não há Composer no host; instale com `--no-dev` |
| `artisan`, `composer.json`, `composer.lock` | dispensáveis em runtime, mas inofensivos |
| o build do Nuxt, dentro de `public/` | a interface, servida na mesma origem da API |

| Não envia | Por quê |
| --- | --- |
| `tests/`, `phpunit.xml`, `.phpunit.result.cache` | não rodam em produção |
| o `.env` local e o `.env.example` | o `.env` local aponta para o SQLite; se subir, derruba o site |
| `node_modules/`, `package.json`, `vite.config.js`, `postcss.config.js`, `tailwind.config.js`, `resources/css/`, `resources/js/` | sobra do scaffolding; a interface é o projeto Nuxt |
| `.git/`, `.github/`, `README.md`, `.editorconfig`, `.gitattributes` | só interessam ao repositório |
| `frontend/` | tem deploy próprio, fora do `htdocs` da API |

**No servidor o arquivo de ambiente se chama `.env`**, não `.env.production`.
Com esse nome a aplicação sobe nos dois cenários: se o `mod_env` estiver ativo,
o `SetEnv APP_ENV production` faz o Laravel procurar `.env.production`, não
achar e cair no `.env`; se não estiver, o `.env` é lido direto. O
`APP_ENV=production` está dentro do próprio arquivo.

### Interface e API na mesma origem

O front é gerado como site estático (`nuxt generate`, porque o host não roda
Node) e publicado **dentro do `public/` do Laravel**. Interface e API passam a
dividir o mesmo endereço, e isso não é detalhe de arrumação: com origens
diferentes o cookie de sessão precisaria de `SameSite=None; Secure` e a API
precisaria liberar CORS com credenciais. Na mesma origem, nada disso é
necessário.

```
condoval.wuaze.com/           -> public/index.html   (Nuxt)
condoval.wuaze.com/_nuxt/…    -> public/_nuxt/…      (assets)
condoval.wuaze.com/api/…      -> public/index.php    (Laravel)
condoval.wuaze.com/up         -> health check
```

Quem separa as duas metades é o [public/.htaccess](public/.htaccess): ele
define `index.html` como página inicial do diretório, manda `api/` e `up` para o
front controller e devolve o `200.html` para qualquer outro caminho que não
corresponda a um arquivo real. Sem essa última regra, recarregar a página numa
rota interna cairia no roteador do Laravel e devolveria um 404 em JSON no lugar
da interface.

**A separação usa o caminho relativo ao diretório, não `%{REQUEST_URI}`.** O
`.htaccess` da raiz já reescreveu a URL para `public/…` antes deste arquivo
rodar, então ali dentro `REQUEST_URI` vale `/public/api/...` e uma condição
ancorada em `^/api` nunca casa — o efeito é a API inteira cair no `200.html`,
que foi exatamente o que aconteceu na primeira publicação.

O build não precisa de nenhuma alteração no código do Nuxt: o `apiBase` já é
`/api` relativo e a troca de dados simulados para a API real é variável de
ambiente do build.

```bash
cd frontend
NUXT_PUBLIC_DATA_SOURCE=api npx nuxt generate   # gera .output/public
```

### Publicar

O caminho normal é o publicador local, que fala FTP direto com o host:

```bash
cd laravel && composer install --no-dev --optimize-autoloader
python tools/deploy-infinityfree.py --dry-run    # mostra o plano
python tools/deploy-infinityfree.py              # publica API + front
cd laravel && composer install                   # devolve o ambiente de testes
```

Para corrigir um arquivo só, sem releitura do `vendor/` inteiro:

```bash
python tools/deploy-infinityfree.py --only public/.htaccess
python tools/deploy-status.py                    # quanto já subiu
python tools/deploy-watch.py                     # acompanha em tempo real
```

Depois de enviar, o script **apaga `bootstrap/cache/packages.php` e
`services.php` no servidor**. Eles listam os service providers descobertos a
partir de `vendor/composer/installed.json`; quando o conjunto de dependências
muda, ficam apontando para classes que o autoloader novo não conhece e a
aplicação inteira responde 500 com `Class ... not found`. Como são derivados, e
não fonte, a única forma de invalidá-los é removê-los — o Laravel os reconstrói
na requisição seguinte. O `vendor/composer/` também é reenviado sempre, mesmo
quando o hash bate: são doze arquivos, e são eles que, errados, derrubam tudo.

Se o manifesto se desencontrar do servidor — o que acontece quando uma
publicação é interrompida —, `--assume-synced` regrava o manifesto a partir da
árvore local sem transferir nada, em vez de reenviar milhares de arquivos
idênticos.

A primeira carga leva horas — são milhares de arquivos, um por operação de FTP,
e a sessão cai com frequência. O publicador trata a queda como caso esperado:
reconecta e repete o arquivo, até seis vezes. Na carga inicial deste projeto a
sessão precisou ser refeita **47 vezes**. A partir da segunda publicação sobe só
o que mudou, e uma alteração de código leva segundos.

As credenciais ficam em `.env.deploy`, na raiz do repositório, fora do git —
copie o `.env.deploy.example` e preencha com os dados de *FTP Details* do
painel. O envio é incremental: o script guarda o md5 de cada arquivo publicado,
sobe só o que mudou e retoma de onde parou se a conexão cair. Ele também se
recusa a publicar enquanto o `vendor/` local tiver dependências de
desenvolvimento, para não gastar milhares de inodes da conta com phpunit e
faker.

O que sobe é decidido por uma **lista de inclusão** dentro do script, e não por
exclusões: um diretório novo só é publicado se alguém o nomear ali. O inverso
— excluir o que não deve subir — erraria silenciosamente no dia em que
aparecesse uma pasta nova.

### Deploy pelo GitHub Actions (alternativo)

[.github/workflows/deploy-infinityfree.yml](../.github/workflows/deploy-infinityfree.yml)
faz o mesmo pelo GitHub: roda a suíte, reinstala as dependências com `--no-dev`,
grava o `.env` a partir do segredo e sincroniza o `htdocs`.

**Ele não dispara por push, de propósito.** Um commit de quem não conhece a
configuração do host publicaria direto em produção. É acionado à mão, em
*Actions → Run workflow*.

Configure antes, em *Settings → Secrets and variables → Actions*:

| Segredo | Conteúdo |
| --- | --- |
| `FTP_SERVER` | o host FTP do painel do InfinityFree |
| `FTP_USERNAME` | o usuário FTP (`if0_…`) |
| `FTP_PASSWORD` | a senha FTP |
| `ENV_PRODUCTION` | o conteúdo inteiro do `.env.production` local |
| `DEPLOY_TOKEN` | o mesmo `DEPLOY_TOKEN` do `.env.production` |

E uma *variable* `APP_URL` com `https://condoval.wuaze.com`, usada só pelo
passo opcional que recria o banco.

Quatro pontos que decorrem do desenho:

- **A sincronização é incremental**, como a do publicador local — é o que mantém
  o uso do FTP dentro do que o InfinityFree autoriza ("just don't cause
  unreasonable load on the FTP server").
- **A primeira execução é pesada**: mesmo com `--no-dev`, o `vendor/` passa de
  quatro mil arquivos. Se ela falhar por tempo ou por limite de conexão, suba o
  `vendor/` uma vez pelo FileZilla e deixe o workflow cuidar das diferenças.
- **O banco não é tocado ao publicar.** Recriar o schema é um passo à parte:
  marque `recreate_database` ao rodar o workflow, ou chame a rota de
  implantação direto. Amarrar isso à publicação apagaria a produção a cada
  entrega.
- **O `.env` nunca entra no repositório.** Ele é escrito no runner a partir do
  segredo e vai direto para o FTP; o workflow aborta se faltar `APP_KEY`.

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

> A configuração de produção foi conferida localmente com
> `php artisan --env=production` e o workflow foi validado como YAML, mas nem o
> deploy nem o FTP foram executados a partir desta máquina.

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

## Seeders: um JSON por tabela

Cada tabela com carga inicial tem duas peças — `database/seeders/{Model}Seeder.php`
e `database/seeders/json/{tabela}.json`. O seeder lê o arquivo, percorre os
registros e, para cada um, monta um array **campo a campo, escrito à mão**, e
chama `Model::create()`. Sem factory, sem faker, sem camada genérica.

```php
$json = File::get(database_path('seeders/json/units.json'));
$data = json_decode($json);

foreach ($data as $item) {
    $array = [
        'block' => $item->block,
        'number' => $item->number,
        'code' => $item->code,
        'status' => $item->status,
    ];

    Unit::create($array);
}
```

`json_decode($json)` sem o segundo argumento: os registros vêm como objetos, e
o acesso é `$item->campo`.

As regras que não se quebram:

- **`create()`, nunca `updateOrCreate()`.** A carga tem de falhar alto. Se
  rodou duas vezes e duplicou, isso é informação: o banco não estava no estado
  que se achava.
- **Nenhum fallback no mapeamento.** `$item->campo`, jamais `?? null`. Dado
  faltando vira erro, não vira nulo em silêncio.
- **As chaves do JSON são os nomes exatos das colunas.** Migration, JSON e
  seeder andam juntos no mesmo commit. `id`, `created_at` e `updated_at` ficam
  de fora, salvo quando o código referencia a linha por id fixo — aí o `id` vai
  nos três, inclusive no `$fillable`, senão o `create()` o ignora calado.
- **O caminho do JSON se lê no seeder, não se deduz do nome da tabela.**
- **A ordem no `DatabaseSeeder` é a das chaves estrangeiras**, não a
  alfabética.

Duas consequências que pegam:

- `DatabaseSeeder` usa `WithoutModelEvents`, então **nenhum evento de model
  dispara na carga**. O que um `booted()` ou um observer preencheria precisa
  estar escrito no JSON. (Os *casts* continuam valendo: por isso `users.json`
  traz a senha em claro e o cast `hashed` a transforma em Argon2id.)
- **Os ids saem da ordem das linhas no arquivo**, e outros JSON apontam para
  eles por número — `reservations.json` tem `"resident_id": 5` porque o Ana
  Silva é o quinto registro de `users.json`. Acrescentar sempre no fim, nunca
  inserir no meio nem reordenar.

### A cadeia da matriz de permissões

A autorização é lida do banco, o banco vem do JSON, o JSON espelha o enum e o
enum espelha o frontend. Cada elo tem a sua trava:

| Elo | Guardado por |
| --- | --- |
| frontend ↔ `UserRole::useCases()` | `PermissionMatrixTest` |
| enum ↔ `role_has_permissions.json` | `PermissionJsonMatrixTest` |
| JSON ↔ banco | `RolePermissionSeeder` |

Sem a trava do meio seriam três cópias soltas, e a divergência apareceria como
um 403 que ninguém explica.

### Testes

`tests/TestCase` aplica o `TestDatabaseSeeder` — só as tabelas de referência,
papéis e permissões. Sem essa carga, todo teste de rota protegida responderia
403 e estaria medindo a falta do vínculo em vez da regra. Dado de domínio cada
teste cria pela factory: depender de registro semeado quebraria o teste quando
alguém acrescentasse uma linha a um JSON.

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
{ "status": false, "message": "Já existe uma reserva...", "error": "Já existe uma reserva..." }

// Exceção capturada pelo controller
{ "status": false, "message": "...", "code": 409, "error": "..." }

// Validação de Form Request — HTTP 422, formato padrão do Laravel
{ "message": "Informe o título do comunicado. (and 1 more error)", "errors": { "title": [ ] } }
```

O `error()` devolve `message` **e** `error` com o mesmo texto. O CLAUDE.md
registra a divergência entre os dois formatos do sistema irmão e manda unificá-la
"antes do primeiro controller, nunca no meio do caminho" — foi o que se fez aqui,
no módulo de autenticação. A chave `error` permanece para não quebrar quem já a
lia; o cliente tem `message` em toda resposta de falha, venha ela de um `error()`,
de um `throwable()` ou da validação.

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

## Autenticação — UC02 / RF02

Três rotas, todas públicas por natureza: quem chega ainda não tem sessão.

| Rota | Efeito |
| --- | --- |
| `POST /api/auth/login` | abre a sessão e devolve `{data: {user}}` |
| `GET /api/auth/session` | quem está autenticado, ou resposta sem `data` |
| `POST /api/auth/logout` | encerra a sessão; idempotente |

A sessão viaja no cookie `HttpOnly` emitido pelo Laravel — **nenhum token vai
para o cliente**. A regra de negócio fica na
[AuthenticateUserAction](app/Http/Actions/AuthenticateUserAction.php); o
controller apenas valida, delega e responde pelo `MessageService`.

Quatro decisões que valem registrar:

- **E-mail desconhecido e senha errada devolvem a mesma recusa**, com o mesmo
  401. Mensagens distintas transformariam a tela de login num verificador de
  quais e-mails estão cadastrados no condomínio.
- **A RN04 é verificada depois da senha**, não antes. Recusar de cara quem está
  inativo revelaria que a conta existe; exigindo a credencial primeiro, só
  descobre a situação quem já provou conhecê-la.
- **A sessão é regenerada no login.** Sem isso, um identificador plantado no
  navegador da vítima antes da autenticação continuaria valendo depois dela.
- **A senha é regravada quando os parâmetros do Argon2id mudam**
  (`Hash::needsRehash`), para que contas antigas não fiquem presas a um custo
  menor que o atual.

A RN04 tem duas metades: a Action recusa o login de quem está inativo, e o
middleware `user.active` encerra a sessão de quem for inativado **durante** o
uso — verificar apenas no login deixaria a sessão aberta valendo até expirar.

## Senhas: Argon2id (RNF02)

O host **suporta** Argon2id — medido no próprio servidor pela rota
`GET /api/deploy/hashing`, que existe porque a hospedagem compartilhada não
publica a configuração do PHP e não há SSH para inspecioná-la:

| | InfinityFree | Local |
| --- | --- | --- |
| PHP | 8.4.25 | 8.2.29 |
| `memory_limit` | 512M | 128M |
| algoritmos | `2y`, `argon2i`, `argon2id` | idem |

Custo de um hash no servidor, por conjunto de parâmetros:

| Parâmetros | Custo |
| --- | --- |
| **m=64MiB t=4 p=1 (em uso)** | **376 ms** |
| m=46MiB t=1 p=1 (OWASP) | 102 ms |
| m=32MiB t=3 p=1 | 144 ms |
| m=19MiB t=2 p=1 (OWASP mínimo) | 58 ms |

Os parâmetros em uso são mais fortes que a recomendação da OWASP e cabem
folgados no limite de memória do host, então foram mantidos. O custo é pago uma
vez por login, e o limitador de 5 tentativas por minuto por conta impede que
ele vire vetor de consumo de CPU.

Três garantias, cada uma com o seu teste:

- **A senha nunca existe em claro.** O cast `hashed` do model cuida de toda
  escrita — seeder, factory e `CreateUserAction` passam por ele. O teste
  confere a coluna direto no banco, e não só o objeto, porque o cast poderia
  estar mascarando um valor já persistido.
- **O custo acompanha a configuração.** `Hash::needsRehash` no login regrava a
  senha quando os parâmetros endurecem, para contas antigas não ficarem num
  custo menor que o atual.
- **"Exclusivamente" é literal.** Um hash de outro algoritmo não autentica.
  Sem tratamento explícito isso devolvia **500 com "Erro ao salvar as
  informações na base de dados"** — o verificador está com `verify` ligado e
  lança exceção —, mensagem que manda quem for investigar olhar para o banco,
  que não tem nada com isso. Hoje a conta recebe a mesma recusa de qualquer
  credencial inválida, e o caminho de volta é a administração redefinir a
  senha, que a regrava no algoritmo certo.

### Duas correções que só apareceram em produção

O SQLite local escondeu as duas; ambas quebraram a carga inicial no host:

- **`Schema::defaultStringLength(191)`.** O MySQL do host limita uma chave a
  1000 bytes, e um `varchar(255)` em utf8mb4 ocupa 1020 — a criação de
  `password_reset_tokens` morria com *"Specified key was too long"*. O limite
  vale para todos os ambientes, e não só para produção: com tamanhos
  diferentes, um valor que cabe no SQLite estoura no servidor e o erro só
  aparece no deploy.
- **A rota de implantação não pode depender do banco.** Ela roda
  `migrate:fresh`, que derruba `sessions` e `cache` — mas o middleware de
  sessão lê `sessions` antes do controller e grava depois, e o limitador lê o
  cache antes. A rota que conserta o schema dependia do schema. Hoje ela
  dispensa o middleware de sessão (`withoutMiddleware`) e a produção usa
  `CACHE_STORE=file`.

Em produção o `AppServiceProvider` **recusa subir** se `HASH_DRIVER` não for
`argon2id` ou se o PHP do host não oferecer o algoritmo. Um driver trocado por
engano não quebra nada visivelmente: a aplicação continua respondendo e passa a
gravar hash mais fraco, sem ninguém perceber, até alguém auditar o banco. É o
tipo de requisito que precisa falhar alto, no deploy, e não em silêncio.

## Vínculo entre morador e unidade

Origem: diagrama de sequência "Gerenciamento de Moradores e Unidades" e a
associação `Usuario "0..*" -- "0..*" Unidade` do diagrama de classes.

| Rota | Efeito |
| --- | --- |
| `GET /api/unit-occupancies` | lista os vínculos, paginada |
| `GET /api/unit-occupancies/{id}` | um vínculo |
| `POST /api/unit-occupancies` | vincula morador a unidade |
| `DELETE /api/unit-occupancies/{id}` | encerra o vínculo |

Todas no grupo `use.case:link-residents-to-units`, que só o administrador
possui.

**`unit_occupancies` é a fonte de verdade do vínculo**, e guarda o histórico
inteiro: encerrar não apaga a linha, apenas marca `is_active = false` e
preenche `ended_at`. É isso que permite saber quem ocupava a unidade na data de
uma ocorrência antiga.

`users.unit_id` é um atalho denormalizado da ocupação vigente, para que
listagens e o payload do usuário não precisem de junção. **Só a
`LinkResidentToUnitAction` escreve nessa coluna**, dentro da mesma transação do
vínculo — é o que impede as duas fontes de divergirem. Ao encerrar um vínculo,
a coluna é reapontada para a ocupação ativa que restou, ou zerada quando não
resta nenhuma.

O que é validado antes de gravar, tudo antes da primeira escrita para que
nenhum `return` antecipado precise desfazer transação pela metade:

- o usuário e a unidade existem;
- o usuário tem perfil de morador — funcionário, síndico e administrador
  respondem pelo condomínio inteiro, e vinculá-los a uma unidade lhes daria um
  endereço que não têm;
- o usuário está ativo e a unidade está ativa;
- não existe outro vínculo vigente entre os dois. O mesmo morador pode ocupar a
  mesma unidade de novo no futuro; o que não pode é ter dois vínculos ativos
  com ela ao mesmo tempo.

### Middleware `unit.linked`

Responde à pergunta "esta pessoa está associada a uma unidade ou ao
condomínio?". Morador precisa de vínculo ativo; síndico, administrador e
funcionário passam sem nenhum, porque respondem pelo condomínio inteiro —
exigir unidade deles travaria a administração do sistema.

Reservar área comum ou abrir ocorrência sem unidade vinculada produziria
registro órfão: não haveria a que unidade cobrar a reserva nem de onde partiu a
ocorrência. Por isso a checagem é de acesso, no grupo de rota, e não validação
de formulário.

### Correções feitas nas migrations

Quatro problemas encontrados ao conferir o schema, todos corrigidos nas
migrations originais — o projeto ainda não tem base em produção, e a carga
inicial é `migrate:fresh`, então não havia o que preservar:

1. **`users.unit_id` não tinha chave estrangeira** e aceitava id de unidade
   inexistente. A restrição é criada na migration de `units`, porque `users` é
   migrada antes e a referência ainda não existiria.
2. **`units` não tinha coluna de situação**, embora o diagrama de classes
   especifique `Unidade.ativa` e o frontend declare `status`.
3. **`occupant_type` gravava em maiúsculas** (`OWNER`/`TENANT`), destoando de
   todos os outros enums do projeto. Virou minúsculas com `App\Enums\OccupantType`.
4. **Nada indexava a busca por vínculo ativo duplicado.** A unicidade em si
   fica na Action, porque índice parcial não é portável entre SQLite e MySQL.

## A chave da aplicação

`APP_KEY` assina o cookie de sessão e criptografa tudo que passa pelo `Crypt`.
Quem a tem **forja a sessão de qualquer usuário sem saber a senha**.

`config/app.php` **não tem valor padrão para ela**, de propósito. O import
deste projeto trazia ali uma chave real como fallback do `env()`, versionada no
repositório: qualquer ambiente que subisse sem a variável passaria a assinar
sessões com uma chave pública, e nada indicaria o problema porque o site
continuaria funcionando. Hoje, faltando a variável, a aplicação falha ao subir.

A chave que estava exposta foi **rotacionada** nos dois ambientes — uma chave
publicada está comprometida, e remover o fallback sem trocá-la não resolveria
nada. O `ApplicationKeyTest` é a guarda de regressão: ele falha se alguém
reintroduzir um padrão no `config/app.php` ou deixar uma chave no
`.env.example`.

Ambiente novo precisa da sua própria: `php artisan key:generate`.

## Perfis e permissões — UC01 / RF01

A autorização é do **spatie/laravel-permission**: papéis e permissões em
tabelas, consultados a cada requisição. A matriz que as alimenta continua em
`App\Enums\UserRole::useCases()`, espelhando
`frontend/app/domain/permissions.ts`.

### Endpoints

Todos no grupo `use.case:manage-residents`, que só o administrador possui —
`manage-residents` é o caso de uso do UC01 na matriz.

| Rota | Efeito |
| --- | --- |
| `GET /api/permissions` | catálogo dos casos de uso, paginado |
| `GET /api/roles` | os perfis com os casos de uso de cada um |
| `GET /api/users/{id}/permissions` | o que a pessoa pode, e por quê |
| `POST /api/users/{id}/permissions` | concede um caso de uso à pessoa |
| `DELETE /api/users/{id}/permissions/{permissão}` | revoga a concessão individual |
| `PATCH /api/users/{id}/role` | troca o perfil, que é a concessão em bloco |

A consulta separa `from_role` de `direct`: sem essa distinção, a administração
não tem como saber o que some se o perfil da pessoa mudar.

Quatro recusas, cada uma com motivo:

- **Conceder o que o perfil já dá** (409). Criaria uma linha que não muda nada
  e que sobreviveria a um rebaixamento de perfil, deixando a pessoa autorizada
  por um caminho que ninguém lembra de ter criado.
- **Conceder duas vezes** (409).
- **Revogar o que veio do perfil** (409). Retirar isso exigiria trocar o
  perfil; aceitar daria a impressão de ter funcionado sem efeito nenhum.
- **Retirar de si mesmo o `manage-residents`** (403). Sem a trava, o último
  administrador se tranca para fora e não sobra ninguém capaz de devolver a
  permissão pela interface.

Essa divisão é o ponto do desenho:

| Camada | Papel |
| --- | --- |
| `UserRole::useCases()` | a matriz **declarada**, versionada e revisável em diff |
| tabelas do Spatie | a autorização **em vigor**, consultada em tempo de execução |
| `SyncUserRoleAction` | o único lugar que materializa uma na outra |

O que se ganha com o banco no meio: a administração pode conceder um caso de
uso a **uma pessoa específica** sem promovê-la de perfil e sem deploy —
`$user->givePermissionTo('manage-residents')`. O enum sozinho não permitia
isso, porque a permissão estava amarrada ao papel em código.

O que se arrisca: duas representações do mesmo fato divergirem. Por isso
`users.role` e o vínculo do Spatie só são escritos pela `SyncUserRoleAction`,
e é ela que a `CreateUserAction`, a factory e o seeder chamam.

A autorização continua sendo resolvida pelo **grupo de rota**, pelo middleware
`use.case:` — nenhum controller verifica permissão. O middleware não mudou de
nome nem de resposta; o que mudou é de onde ele lê.

Três comportamentos que valem registrar:

- **RN04 continua valendo acima da permissão.** `hasUseCase()` exige usuário
  ativo antes de consultar a autorização: um administrador inativado perde o
  acesso mesmo mantendo todas as permissões no banco.
- **Permissão ausente nega, não estoura.** O `can()` devolve falso para um nome
  que não existe, enquanto o `hasPermissionTo()` do Spatie lançaria
  `PermissionDoesNotExist` e viraria 500. Uma permissão ainda não semeada se
  comporta como ausente e a rota responde 403 — o mesmo que o CLAUDE.md
  descreve para a tabela `pages` do sistema irmão.
- **Trocar o perfil retira as autorizações do anterior.** `syncRoles`, e não
  `assignRole`: sem isso a pessoa acumularia as permissões dos dois papéis.

### O cache do Spatie, que custou duas falhas

O `findOrCreate` do Spatie procura na coleção que o `PermissionRegistrar`
mantém em memória, **não no banco**. Se essa coleção foi carregada quando a
tabela ainda estava vazia — o que acontece logo depois de um `migrate:fresh` —
ele conclui que a permissão não existe e tenta inserir uma que já está lá,
estourando a restrição de unicidade. Daí os `forgetCachedPermissions()` antes
de resolver e depois de conceder, na `SyncUserRoleAction`.

### Tamanhos de coluna na migration do Spatie

A migration publicada usa `string('name')` sem tamanho, o que com o
`defaultStringLength(191)` daria um índice único `(name, guard_name)` de
2 x 191 x 4 = 1528 bytes — acima do limite de 1000 do MySQL do host. Foram
fixados em 125 e 25, que dão 600 bytes. A própria migration do Spatie alerta
para isso nos comentários.

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
