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

### Conferir produção depois de um merge na main

Há **dois publicadores escrevendo no mesmo servidor** — este script e o
workflow do GitHub Actions, que dispara a cada push na `main`. Cada um guarda o
próprio manifesto do que enviou, nenhum sabe o que o outro fez, e outras
pessoas também podem publicar. Confiar no processo não basta:

```bash
python tools/verify-production.py
```

Ele baixa do servidor os arquivos que **derrubam a aplicação inteira quando
ficam defasados** — `bootstrap/app.php`, `routes/api.php`, `config/`,
`vendor/autoload.php` e `vendor/composer/` — e compara o md5 com a árvore
local; depois chama a
interface e os endpoints principais, resolvendo o desafio anti-robô do host.
Sai com código 1 se algo divergir, então serve como passo de pipeline.

Isso já pegou um incidente real: um deploy automático publicou código pela
metade e produção respondia `Target class [password.change] does not exist`,
porque o `bootstrap/app.php` não havia chegado.

Quando o `vendor/` local está com as dependências de desenvolvimento, os
arquivos do autoloader legitimamente diferem dos do servidor, que recebe
`--no-dev`. O script os marca como não comparáveis em vez de acusar divergência
— ferramenta que dá alarme falso para de ser lida. **Mas esses são justamente
os que mais derrubam produção**, então, quando a verificação importa de
verdade, rode-a com o `vendor/` local em `--no-dev`:

```bash
cd laravel && composer install --no-dev --optimize-autoloader
python ../tools/verify-production.py
cd laravel && composer install
```

Foi essa a lacuna que deixou passar uma queda real: o `vendor/autoload.php` do
servidor chamava uma classe de inicialização que o `autoload_real.php` de lá já
não definia. O nome dessa classe muda a cada regeneração do autoloader, e
desencontrado o PHP morre **antes de qualquer saída** — nem o log nem a página
de erro chegam a existir. Por isso o `vendor/autoload.php` entrou no reenvio
obrigatório do publicador, ao lado de `vendor/composer/`.

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
| `app/Http/Utils/AuditUtil.php` | o "de → para" de uma edição, para a auditoria |
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

### A trava de paridade com o frontend

O `PermissionMatrixTest` compara o enum com uma cópia escrita dentro do próprio
teste — ou seja, compara o enum consigo mesmo. Ele não enxergou duas
divergências reais que entraram pelo frontend.

O `FrontendPermissionParityTest` **lê o arquivo do frontend** e compara de
verdade. Divergência não quebra nada visivelmente: a interface oferece uma ação
que a API recusa, ou esconde uma que ela aceitaria, e a pessoa vê um 403 sem
explicação ou um botão que nunca aparece.

Ele carrega uma lista de **divergências conhecidas**, visível no código, para
poder passar sem deixar de vigiar: qualquer divergência nova quebra a suíte, e
riscar uma linha da lista é o que fecha a pendência. Hoje há duas, ambas
dependendo de alteração no `permissions.ts`:

- `manage-users` existe só no frontend. Enquanto não existir aqui, uma rota
  protegida por ele responderia 403, porque a permissão não está no banco.
  Falta decidir se é outro nome para `manage-residents` ou um caso de uso novo.
- `update-own-profile` vale para os quatro perfis aqui, e só para o morador lá.

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

> Não existe tabela `type_logs`: a referência dos tipos de log é o enum
> `App\Enums\TypeLogEnum`, e `logs.type_log_id` não tem chave estrangeira. Os
> ids do enum são contrato — os registros já gravados apontam para eles. Veja a
> seção [Auditoria](#auditoria--rnf04--uc-t03--rn16).

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
/api/occurrences?filters=created_at:>=:2026-09-01;status:=:completed&sort=created_at&direction=desc
/api/users?filters=status:=:active&search=silva&limit=50
```

Os nomes de coluna vindos do cliente são validados contra o schema real da
tabela, menos os `$hidden` do model. Uma página traz no máximo 100 linhas
(RNF01); quem precisa de tudo pagina. O relatório de ocorrências tem contrato
próprio, com exportação completa — ver "Relatórios — RF06".

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

## Unidades — RN03

| Rota | Autorização |
| --- | --- |
| `GET /api/units` | `manage-units` (síndico e administrador) |
| `GET /api/units/{id}` | `manage-units` |
| `POST /api/units` | `manage-units` |
| `PATCH /api/units/{id}` | `manage-units` |
| `POST /api/units/{id}/deactivate` | `manage-units` |

A reativação é o `PATCH` com `status: active`. O código é normalizado em
maiúsculas — sem isso `a-101` e `A-101` virariam duas unidades, e o vínculo do
morador passaria a depender de como alguém digitou.

**Não se inativa unidade com morador vinculado.** A
`LinkResidentToUnitAction` recusa vincular a unidade inativa; inativar uma que
ainda tem ocupação criaria exatamente o estado que o sistema não deixa criar —
alguém ligado a uma unidade fora de operação, sem como refazer o vínculo. A
recusa diz quantos moradores são, para a administração medir o trabalho. A
regra vale no `deactivate` e no `PATCH` igualmente, porque mora na Action.

Inativar **nunca apaga**: reservas, ocorrências e o histórico de ocupação
continuam apontando para a unidade.

**O síndico também altera unidades.** A nota do diagrama de sequência é
explícita — "alterações estruturais de unidades restritas ao
**Síndico/Administrador**" —, e a matriz foi alinhada a ela: `manage-units`
passou a valer para os dois perfis.

É o **único** caso de uso administrativo que o síndico divide. O cadastro de
pessoas continua só do administrador, por `manage-residents`.

> **Pendente no frontend:** `permissions.ts` ainda dá `manage-units` apenas ao
> administrador. Enquanto não for alterado, a interface esconde do síndico a
> tela de unidades, embora a API já o autorize.

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

## Gestão de usuários — UC01 / RF01

| Rota | Autorização |
| --- | --- |
| `GET /api/users` | `manage-residents` |
| `GET /api/users/{id}` | `manage-residents` |
| `POST /api/users` | `manage-residents` |
| `PATCH /api/users/{id}` | `manage-residents` |
| `POST /api/users/{id}/deactivate` | `manage-residents` |
| `GET /api/users/me` | `update-own-profile` |
| `PATCH /api/users/me` | `update-own-profile` |
| `PATCH /api/users/me/password` | `update-own-profile` |

`manage-residents` pertence **só ao administrador**. O síndico responde por
comunicados e relatórios, não pelo cadastro de pessoas — e o sistema é de um
condomínio só, então não há escopo por condomínio a aplicar.

**Uma porta por mudança.** O `PATCH /api/users/{id}` edita qualquer dado,
inclusive perfil e situação: a reativação é ele com `status: active`. A rota
dedicada `PATCH /users/{id}/role`, que existia do card de permissões, foi
removida — duas portas para a mesma mudança são duas regras para manter em
acordo, e a que ficasse esquecida viraria a brecha.

**`/users/me` é outra coisa.** O id vem da sessão, nunca da requisição, então
não existe como alcançar o cadastro alheio por essa porta. Os quatro perfis
têm `update-own-profile` — antes só o morador tinha, o que deixava
administrador e síndico sem corrigir o próprio nome.

### Primeiro acesso com senha temporária

A conta cadastrada pela administração nasce com uma senha temporária gerada na
hora e marcada com `must_change_password`. **Quem cadastra nunca escolhe a
senha de ninguém**, e nunca existe senha padrão.

**A senha volta no corpo da resposta do cadastro**, para quem cadastrou
repassá-la à pessoa. É o canal em uso enquanto não há envio de e-mail
configurado — e é por ela ser **ditada**, e não lida de um e-mail, que o
formato é palavra conhecida mais três dígitos em oito caracteres: `casco830` se
diz numa frase e se digita sem erro, enquanto uma sequência aleatória se perde
na transcrição e volta como "não consigo entrar".

A troca é **menos entropia por mais usabilidade, e é deliberada**. São 133
palavras de cinco letras sem acento, mais um número de três dígitos: 119.700
combinações, cerca de 17 bits. O que sustenta a escolha não é a senha, é o que
a cerca:

- ela só serve uma vez, porque o primeiro acesso obriga a trocá-la;
- o limitador do login permite 5 tentativas por minuto **por conta**;
- cada tentativa custa ~376 ms de Argon2id no servidor.

Nessas condições, varrer o espaço inteiro levaria cerca de **17 dias** —
muito mais que as horas de vida pretendidas para a senha. O
`TemporaryPasswordTest` cobra um piso para esse espaço, porque encolher a lista
de palavras enfraqueceria toda senha do sistema sem mudar uma linha de lógica.

**O risco residual** é a conta cadastrada e nunca acessada: a senha fraca fica
válida indefinidamente. Quando houver envio de e-mail — ou uma rota para a
administração regerar a temporária —, vale expirar a senha não usada.

Enquanto a marca estiver ligada, a sessão abre mas não alcança mais nada: o
middleware `password.change` recusa com `403` e o código
`PASSWORD_CHANGE_REQUIRED`, para o cliente levar à tela certa em vez de exibir
um "sem permissão" que não explica nada.

Duas rotas ficam **fora** desse bloqueio, cada uma por um motivo:

- `PATCH /users/me/password` é a saída dele. Pôr o middleware ali trancaria a
  pessoa do lado de fora da única porta capaz de destrancá-la.
- `GET /auth/session` é como o cliente descobre quem está autenticado.
  Bloqueá-la deixaria a interface sem saber sequer que precisa pedir a troca.
  O `must_change_password` vai no payload do usuário justamente para isso.

A troca exige a senha atual — sem isso, uma sessão sequestrada viraria posse
permanente da conta — e regenera a sessão, porque a credencial mudou.

### As travas da inativação

Inativar **nunca apaga**: `status = inactive`, e as reservas, ocorrências e
linhas de auditoria continuam apontando para a pessoa. O efeito é imediato para
quem já está dentro, pelo `user.active`.

Duas recusas impedem o sistema de ficar sem administração, e valem tanto no
`deactivate` quanto no `PATCH` com `status: inactive`:

- **ninguém se inativa** — quem o faz não volta pela interface;
- **não se inativa o último administrador ativo** — sem ele não sobra quem
  cadastre, edite ou reative ninguém, e o sistema só voltaria por acesso direto
  ao banco.

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

## Painel inicial

`GET /api/dashboard`, para qualquer pessoa logada. O painel é montado pelos
casos de uso de quem pede — não pelo perfil —, então uma concessão individual
traz a seção junto. Seção que a pessoa não pode ver não vem na resposta, nem
vazia.

| Seção | Aparece para quem tem | Conteúdo |
| --- | --- | --- |
| `my_occurrences` | `create-occurrence` ou `track-own-occurrences` | chamados próprios abertos, por status, os 5 mais recentes |
| `my_reservations` | `view-own-reservations` | próximas reservas próprias, pendentes |
| `my_assignments` | `view-assigned-occurrences` | para iniciar, em andamento, concluídos no mês, fila do mais antigo |
| `occurrence_queue` | `analyze-occurrences`, `assign-occurrence` ou `close-any-occurrence` | chamados por etapa e os 5 que esperam há mais tempo |
| `reservations` | `manage-reservations` ou `approve-or-reject-reservation` | a aprovar, de hoje, dos próximos 7 dias, próximas 5 |
| `units` | `manage-units` | total, ativas, inativas, ocupadas, vagas |
| `people` | `manage-residents` | ativos por perfil, contas inativas, primeiro acesso pendente |
| `month` | `generate-reports` | o mês corrente, com o resumo dos dois relatórios |
| `notices` | `view-notices` ou `publish-notices` | publicados, os 3 mais recentes; rascunhos só para quem publica |
| `recent_activity` | `view-audit-reports` | as 5 últimas ações da auditoria, sem os dados gravados nem o IP |

Toda resposta traz também `user` (nome, perfil e unidade) e `cards`: os
indicadores da fileira do topo, prontos (`key`, `label`, `value`, `note`), na
ordem de importância para quem está olhando — primeiro o que é da pessoa,
depois o que ela precisa resolver, por último o panorama.

Com dez mil chamados e dez mil reservas, o painel do administrador, o mais
pesado, abre em cerca de 10 ms no SQLite, com 21 consultas — as mesmas com o
dobro de dados (`DashboardPerformanceTest`).

## Ocorrências e denúncias — RF04 / RN05 / RN06 / UC6 a UC12

| Rota | Autorização | Caso de uso |
| --- | --- | --- |
| `POST /api/occurrences` | `create-occurrence` ou `open-occurrence-for-resident` | UC6 Registrar ocorrência e UC7 Registrar denúncia |
| `GET /api/occurrences`, `/{id}`, `/{id}/history` | `track-own-occurrences`, `view-assigned-occurrences`, `analyze-occurrences` ou `close-any-occurrence` | UC9 Consultar atribuídas e UC12 Acompanhar minhas solicitações |
| `PATCH /api/occurrences/{id}/status` | `analyze-occurrences` ou `update-occurrence-progress` | UC10 Atualizar andamento (status) |
| `PATCH /api/occurrences/{id}` | `analyze-occurrences` ou `update-occurrence-progress` | UC10 Atualizar andamento (status e anotação) |
| `PATCH /api/occurrences/{id}/assign` | `assign-occurrence` ou `hand-over-occurrence` | UC8 Encaminhar ocorrência para funcionário |
| `POST /api/occurrences/{id}/finish` | `finish-occurrence` ou `close-any-occurrence` | UC11 Finalizar atendimento |
| `/api/occurrences/{id}/attachments` | quem enxerga o chamado | imagens do chamado (até 5) |
| `/api/occurrence-types` | leitura por quem abre chamado; escrita por `manage-occurrence-types` | os tipos e a natureza de cada um |

Cada pessoa enxerga só o seu recorte (`ScopeOccurrencesAction`): o morador, os
próprios chamados; o funcionário, os atribuídos a ele; síndico e
administração, a fila inteira. Chamado fora do recorte responde 404.

**Pedidos simultâneos (OCR-10).** Toda mudança de chamado — status, anotação,
encaminhamento, finalização, imagem — relê o chamado com trava
(`Occurrence::lockForChange`) dentro da transação antes de conferir o status.
Sem isso, o clique duplo em "finalizar" passava duas vezes pela checagem: no
MySQL 8.0, seis pedidos simultâneos de finalizar o mesmo chamado eram os seis
aceitos, com seis históricos de conclusão. Com a trava, um é aceito e os
outros cinco recebem 409; o mesmo vale para duas análises ao mesmo tempo e
para a sexta imagem quando só resta uma vaga. Provado pelo `OccurrenceRaceTest`
(só roda contra MySQL, como o `ReservationRaceTest`).

### UC7 — denúncia

A especificação tem dois casos de uso de abertura no RF04: "UC6 - Registrar
ocorrência" e "UC7 - Registrar denúncia". A denúncia é um chamado como os
outros — do morador que abriu, com status (RN05), tratado pelo mesmo fluxo
(RN06) —, e o que a separa é o assunto: barulho, uso indevido de área,
descumprimento do regimento. Por isso a natureza fica no **tipo**:

- `occurrence_types.kind` é `maintenance` (Manutenção) ou `complaint`
  (Denúncia). Tipo novo nasce como manutenção; a administração decide quais são
  denúncia (`POST`/`PATCH /api/occurrence-types` com `kind`), e a mudança fica
  na auditoria.
- Registrar denúncia é abrir chamado com um tipo de denúncia. Cada ocorrência
  devolve `occurrence_type.kind`.
- `GET /api/occurrences?kind=complaint` lista só as denúncias (somado ao
  recorte de quem pede); o relatório aceita o mesmo `kind` e traz `by_kind`.
- A carga traz dois tipos de denúncia: "Barulho e sossego" e "Conduta e
  regimento interno". Em produção, a migration deixa todos os tipos existentes
  como manutenção — marcar os de denúncia é decisão da administração, não um
  palpite da migration pelo nome.

Não há caso de uso próprio de permissão para a denúncia: ela usa o
`create-occurrence` do morador. Um caso novo exigiria mudar a matriz do
frontend junto (`permissions.ts`), e a especificação dá aos dois casos o mesmo
ator e as mesmas regras.

## Comunicados — RF05 / RN07 / RN03

| Rota | Autorização | Efeito |
| --- | --- | --- |
| `GET /api/notices` | `view-notices` ou `publish-notices` | mural, paginado, do mais recente para o mais antigo |
| `GET /api/notices/{id}` | `view-notices` ou `publish-notices` | um comunicado |
| `POST /api/notices` | `publish-notices` | publica, ou guarda como rascunho com `status: draft` |
| `PATCH /api/notices/{id}` | `publish-notices` | corrige título ou conteúdo; `status: published` publica um rascunho |
| `POST /api/notices/{id}/publish` | `publish-notices` | publica um rascunho (`Comunicado.publicar()`) |
| `DELETE /api/notices/{id}` | `publish-notices` | exclui (RN03) |

`publish-notices` é do síndico e da administração; `view-notices`, do morador
(o funcionário só lê o mural por concessão individual).

- **Quem só lê vê só o publicado (RN18).** O rascunho aparece para quem
  publica e responde 404 para os demais, a mesma resposta de um comunicado
  que não existe — a diferença revelaria que há um aviso em preparação.
- **Autor e data de publicação são do servidor.** O corpo traz só título,
  conteúdo e, opcionalmente, `status`; `author_id` e `published_at` enviados
  são ignorados, para ninguém assinar em nome do síndico nem antedatar aviso.
- **Editar não apaga o que estava escrito.** O texto anterior vai para a
  auditoria (`notice.updated`, de → para), e autor e `published_at` não mudam.
  Um comunicado publicado não volta a rascunho (409): quem leu já leu, e o
  jeito de retirá-lo é excluir.
- **Excluir apaga a linha, não o registro.** Título, texto, autor e datas vão
  para o `data_log` da auditoria: aviso oficial que some sem rastro vira
  disputa de "ninguém avisou".
- Do autor saem só id e nome, e o parâmetro `relations` é descartado na
  listagem, porque por ele sairia o cadastro inteiro.

## Relatórios — RF06 / UC20

Dois relatórios, no grupo `use.case:generate-reports` (síndico e
administração). Leem o condomínio inteiro, sem o recorte por pessoa das telas
de acompanhamento, e dividem o mesmo contrato: período, paginação e exportação
vêm de `ReportRequest`, e a lista sai pelo `PaginateReportAction`, com o mesmo
formato e o mesmo teto de exportação nos dois.

### Ocorrências e manutenções

`GET /api/reports/occurrences`

| Parâmetro | Obrigatório | Efeito |
| --- | --- | --- |
| `from`, `to` | sim | período de **abertura**, `AAAA-MM-DD`, os dois dias inteiros |
| `occurrence_type_id` | não | filtra a lista por tipo |
| `status` | não | filtra a lista por status |
| `kind` | não | filtra a lista por natureza: `maintenance` ou `complaint` (UC7) |
| `page`, `limit` | não | paginação da lista; `limit` até 100 |
| `export` | não | `true` traz a lista inteira numa resposta (RN12) |

A resposta segue o `ReportQuery` do frontend, e traz também `by_kind`
(manutenção e denúncia, sempre as duas):

```jsonc
{
  "status": true,
  "message": "Relatório de ocorrências de 01/10/2026 a 31/10/2026.",
  "data": {
    "period": { "from": "2026-10-01", "to": "2026-10-31" },
    "summary": { "total": 7, "open": 4, "completed": 2, "cancelled": 1,
                 "resolved": 1, "unresolved": 1, "average_resolution_hours": 26.5 },
    "by_status": [{ "status": "open", "label": "Aberto", "total": 2 }],
    "by_type": [{ "occurrence_type_id": 1, "name": "Manutenção predial", "total": 3 }],
    "occurrences": { "data": [], "total": 7, "page": 1, "per_page": 10, "last_page": 1 }
  }
}
```

Três decisões que valem registrar:

- **Os indicadores olham o período inteiro; tipo e status filtram só a
  lista.** É o comportamento da tela: trocar a aba de status muda a tabela,
  não o painel de cima. `by_status` traz todos os status, mesmo os zerados;
  `by_type`, só os tipos usados no período, e é ela que alimenta o filtro.
- **Encerrar não é resolver.** `resolved` e `unresolved` separam o problema
  que acabou do que só foi fechado; concluída sem o campo conta como
  resolvida, que é o padrão da finalização. O tempo médio vai da abertura à
  conclusão, só das concluídas.
- **A exportação tem teto** (`reports.occurrences.max_export_rows`, 5000).
  Acima dele a API responde 422 pedindo um período menor, pela contagem, antes
  de montar as linhas: a hospedagem corta o script em 60 segundos.

Desempenho (RNF01), fixado no `OccurrenceReportPerformanceTest` com 10 mil
chamados em mil dias: o pior caso, a exportação de três meses, fica perto de
100 ms no SQLite, e são sempre 9 consultas, com 50 linhas, com 100 ou
exportando. O índice em `occurrences.created_at` é o que o período usa.

### Reservas das áreas comuns

`GET /api/reports/reservations`

| Parâmetro | Obrigatório | Efeito |
| --- | --- | --- |
| `from`, `to` | sim | período pela **data da reserva**, `AAAA-MM-DD`, os dois dias inclusos |
| `common_area_id` | não | filtra a lista por área |
| `status` | não | filtra a lista por status |
| `page`, `limit`, `export` | não | iguais aos do relatório de ocorrências |

```jsonc
{
  "status": true,
  "message": "Relatório de reservas de 01/10/2026 a 31/10/2026.",
  "data": {
    "period": { "from": "2026-10-01", "to": "2026-10-31" },
    "summary": { "total": 12, "pending": 2, "approved": 8, "rejected": 1, "cancelled": 1 },
    "by_status": [{ "status": "approved", "label": "Aprovada", "total": 8 }],
    "by_area": [{ "common_area_id": 1, "name": "Churrasqueira", "total": 7,
                  "pending": 1, "approved": 5, "rejected": 0, "cancelled": 1 }],
    "reservations": { "data": [], "total": 12, "page": 1, "per_page": 10, "last_page": 2 }
  }
}
```

- **O período é o da data da reserva**, não o de quando foi pedida: uma
  reserva para dezembro pedida em outubro é uso de dezembro.
- **`by_area` traz todas as áreas, inclusive as sem reserva.** Diferente dos
  tipos de ocorrência, a área parada é informação: é o salão que ninguém usou
  no mês.
- Cada linha traz a área, o morador com nome e unidade, o motivo da recusa e
  `full_day`, que marca a diária — a reserva sem horário, que ocupa o dia
  inteiro.

Com a carga da UC-T01 (10 mil reservas em mil dias), o pior caso também é a
exportação de três meses, perto de 100 ms, com 7 consultas fixas. Não foi
preciso índice novo: `reservations.date` já tinha.

## Auditoria — RNF04 / UC-T03 / RN16

"O sistema deve manter logs de auditoria detalhados para todas as operações
críticas realizadas por administradores e funcionários." Toda operação grava
na tabela `logs` pelo `CreateLogAction`; a administração lê pelo
`GET /api/audit-logs`.

### O que cada registro guarda

| Coluna | Conteúdo |
| --- | --- |
| `type_log_id` | a categoria (`TypeLogEnum`), que define o tipo do registro afetado |
| `action` | a operação (`AuditAction`), como `reservation.approved` |
| `subject_id` | o id do registro afetado; o tipo vem do `type_log_id` (`TypeLogEnum::entity()`) |
| `user_id`, `user_role` | quem fez, e o perfil que tinha **naquele momento** |
| `description` | a frase em português, com id e nome do registro |
| `data_log` | os detalhes: o "de → para" das edições, a cópia do que foi excluído, motivo e justificativa |
| `ip` | guardado, mas nunca sai da API (`$hidden` do model) |
| `created_at` | a hora do servidor |

Autor, perfil, hora e IP vêm sempre do servidor, nunca do corpo da requisição.

Regras:

- **Toda gravação declara ação e registro.** `CreateLogAction::execute()` ganhou
  os argumentos `action:` e `subjectId:`; o `AuditLogTest` varre `app/` e falha
  se uma gravação nova esquecer um dos dois. Uma ação gravada com o tipo de
  outra entidade é recusada com `LogicException`.
- **Edição guarda só o que mudou, de → para.** `AuditUtil::diff()` é chamado
  entre o `fill()` e o `save()` — depois do save o Laravel 11 não guarda mais o
  valor anterior. Campos ocultos (a senha) nunca entram, e "22:00" reenviado
  sobre um "22:00:00" gravado não conta como mudança.
- **O perfil é copiado, não consultado.** O funcionário promovido a síndico
  continua aparecendo como funcionário nas ações de antes da promoção.
- **A trilha só cresce.** O model `Log` recusa alteração e exclusão; não há
  rota que altere ou apague um registro.
- **Aprovar e reprovar reserva agora gravam log.** Era a única operação
  administrativa sem rastro (`ReviewReservationAction`, pelo
  `PATCH /api/reservations/{id}/status`).
- Exclusões guardam uma cópia do registro; a do tipo de ocorrência passou a
  guardar também.

| Entidade (`entity`) | Ações |
| --- | --- |
| `user` | `auth.login`, `auth.logout`, `user.created`, `user.updated`, `user.deactivated`, `user.profile_updated`, `user.password_changed`, `user.permission_granted`, `user.permission_revoked` |
| `unit` | `unit.created`, `unit.updated`, `unit.deactivated` |
| `unit_occupancy` | `unit_occupancy.created`, `unit_occupancy.ended` |
| `common_area` | `common_area.created`, `common_area.updated`, `common_area.deleted` |
| `reservation` | `reservation.created`, `reservation.approved`, `reservation.rejected`, `reservation.cancelled` |
| `occurrence` | `occurrence.created`, `occurrence.assigned`, `occurrence.reassigned`, `occurrence.status_updated`, `occurrence.progress_noted`, `occurrence.completed`, `occurrence.attachment_added`, `occurrence.attachment_removed` |
| `occurrence_type` | `occurrence_type.created`, `occurrence_type.updated`, `occurrence_type.deleted` |
| `notice` | `notice.drafted`, `notice.published`, `notice.updated`, `notice.deleted` |
| `system` | `system.seeded` (a carga de demonstração) |

### Consulta

`GET /api/audit-logs`, só com `view-audit-reports` (o administrador, RN16).
Segue o contrato dos relatórios operacionais.

| Parâmetro | Efeito |
| --- | --- |
| `from`, `to` | período, obrigatório, com os dois dias inteiros |
| `entity` | uma das entidades da tabela acima |
| `entity_id` | o histórico de um registro; exige `entity` (o 7 de uma reserva não é o 7 de uma ocorrência) |
| `user_id` | o responsável |
| `role` | o perfil de quem fez, na época: `admin`, `syndic`, `employee`, `resident` |
| `action` | uma ação da tabela acima |
| `page`, `limit`, `export` | como nos relatórios; a exportação tem teto de 5000 linhas (`config/reports.php`) |

A resposta traz `period`, `summary` (total e quanto por perfil), `by_role`,
`by_entity` e `logs` (`data`, `total`, `page`, `per_page`, `last_page`). Cada
linha tem `id`, `action`, `action_label`, `entity`, `entity_label`,
`entity_id`, `description`, `user` (id e nome), `user_role`,
`user_role_label`, `metadata` e `created_at`. Os nomes seguem o `AuditLog` do
frontend.

Como nos outros relatórios, **os indicadores olham o período inteiro**; os
filtros recortam só a lista. Registros gravados antes desta versão aparecem com
`action` e `entity_id` nulos e só a frase.

Com 20 mil registros em mil dias, nenhuma consulta passou de 65 ms no SQLite
nem de 50 ms no MySQL 8.0, com 5 consultas fixas por página ou exportação
(`AuditReportPerformanceTest`). Índices novos: `created_at`,
`(type_log_id, subject_id)` e `(action, created_at)`.

### Implantação

A migration `2026_11_24_000001_add_audit_fields_to_logs_table` acrescenta
`action`, `subject_id` e `user_role` e preenche `user_role` dos registros
antigos com o perfil atual do autor. **Rode-a logo depois de publicar o
código**, pelo `POST /api/deploy/migrate` com `fresh=false`, que não apaga
nada: até lá, toda operação que grava log falha por coluna inexistente.

## Áreas comuns — AreaComum

| Rota | Quem | Efeito |
| --- | --- | --- |
| `GET /api/common-areas` | morador e administração | lista, por nome, todas as áreas — inclusive as indisponíveis |
| `GET /api/common-areas/{id}` | morador e administração | detalhe |
| `POST /api/common-areas` | administração | cadastra |
| `PATCH /api/common-areas/{id}` | administração | edita o que vier, a situação inclusive |
| `DELETE /api/common-areas/{id}` | administração | exclui a área que nunca foi reservada |

Consultar alcança `view-common-areas`, `request-reservation` e
`manage-reservations`; escrever, só `manage-reservations`. O diagrama de casos
de uso não tem um caso próprio para administrar áreas, e criar um novo exigiria
mudar também a matriz do frontend — por isso a escrita ficou com o caso de
uso de gerenciar reservas.

```json
{
  "name": "Piscina", "description": "Piscina adulto e infantil.", "capacity": 30,
  "opening_time": "09:00", "closing_time": "20:00", "max_reservation_minutes": 180,
  "requires_approval": false, "status": "available"
}
```

- **Nome único**, até 120 caracteres.
- **Horários obrigatórios** (`opening_time` e `closing_time`, o
  `horarioInicio` e o `horarioFim` do diagrama). Podem mudar, mas não ser
  apagados: sem eles não há grade e a reserva é recusada. Regras, todas em
  `CommonArea::hoursProblem()`:
  - formato `HH:MM`; o fechamento aceita também **`24:00`**, a meia-noite que
    encerra o dia, e `00:00` no fechamento quer dizer o mesmo — é gravado e
    devolvido como `24:00`, para nunca ficar "antes" da abertura;
  - o funcionamento é **no mesmo dia**: fechar às 02:00 abrindo às 18:00 é
    recusado (virar a meia-noite não é suportado);
  - a área fica aberta **pelo menos 30 minutos**, um bloco de reserva;
  - na edição, mandar só um dos horários confere com o outro já gravado, e a
    recusa aponta para o campo enviado (`errors.opening_time` ou
    `errors.closing_time`).

  As respostas trazem sempre `HH:MM`, mesmo com o MySQL guardando `HH:MM:SS`.
  A reserva segue o mesmo contrato: o fim do último bloco de uma área que
  fecha à meia-noite é `"end_time": "24:00"` — é o que a tela de reservas
  envia — e `"00:00"` no fim também é aceito como meia-noite. O MySQL guarda
  `24:00:00` numa coluna `TIME` sem problema (testado contra o 8.0).
- **Mudar os horários vale na hora para a disponibilidade e para a criação de
  reserva**: as duas leem a área a cada consulta. As reservas já feitas que
  ficaram fora do novo horário não são mexidas; a resposta da edição diz
  quantas são.
- **`max_reservation_minutes`** é opcional, a partir de 30 e múltiplo de 30 (o
  tamanho do bloco). Sem ele vale o padrão de 2 horas.
- **`capacity`** é opcional; vazia é gravada como 0, que é como a tabela diz
  "não informada".
- **`status`** é a situação ativa: `available` aceita reserva,
  `unavailable` tira a área do calendário. Sem informar, a área nasce
  disponível e sem exigir aprovação.
- **A listagem não pagina na prática**: sem `limit`, devolve até 100 áreas,
  porque a tela de reservas pede a lista inteira. `relations` é descartado —
  por ele sairiam as reservas da área com quem reservou.
- **Mudar as regras não mexe nas reservas já feitas.** Ao tornar a área
  indisponível ou mudar horário ou duração, a resposta avisa quantas reservas
  futuras pendentes ou aprovadas não se encaixam mais
  (`"... 2 reservas futuras pendentes ou aprovadas não se encaixam mais nas
  regras da área: reveja-as na agenda de reservas."`). Cancelar ou recusar é
  decisão da administração, reserva a reserva.
- **Área com reserva não é excluída** (409), nem que a reserva esteja
  cancelada: a tabela de reservas apaga em cascata com a área e o histórico
  dos relatórios iria junto. Nesse caso, torne-a indisponível. A exclusão
  guarda uma cópia da área na auditoria.
- Cadastro, edição e exclusão ficam na auditoria (`TypeLogEnum::COMMON_AREA`).

## Disponibilidade das áreas comuns — UC03 / RF03 / RN01 / RN02

Duas consultas, as duas anônimas (RN14): dizem quando a área está ocupada,
nunca por quem. Alcançam `view-common-areas` e `request-reservation` (morador)
e `manage-reservations` (administração).

| Rota | Devolve |
| --- | --- |
| `GET /api/common-areas/{id}/availability` | o calendário já calculado: status do dia, blocos de 30 minutos, períodos livres e se cabe diária |
| `GET /api/common-areas/{id}/occupancy` | só os períodos tomados (`date`, `start_time`, `end_time`), para quem monta o calendário sozinho — é a que o front chama hoje |

As duas recebem `start_date` e `end_date` (`AAAA-MM-DD`, ambos inclusos), com
no máximo 62 dias por consulta.

```jsonc
{
  "status": true,
  "message": "Disponibilidade da área 3 Quadra Poliesportiva retornada.",
  "data": {
    "common_area": { "id": 3, "name": "Quadra Poliesportiva", "status": "available",
                     "opening_time": "08:00", "closing_time": "22:00",
                     "max_reservation_minutes": 120, "requires_approval": false },
    "start_date": "2026-11-24", "end_date": "2026-11-30",
    "slot_minutes": 30,
    "days": [{
      "date": "2026-11-24",
      "status": "partial",             // available | partial | occupied | unavailable
      "label": "Parcialmente ocupado",
      "reason": null,                   // past_date | area_unavailable | no_opening_hours | closed_for_today
      "reason_label": null,
      "full_day_available": false,
      "occupied_periods": [{ "start_time": "10:00", "end_time": "12:00" }],
      "free_periods": [{ "start_time": "08:00", "end_time": "10:00", "minutes": 120 },
                       { "start_time": "12:00", "end_time": "22:00", "minutes": 600 }],
      "slots": [{ "start_time": "08:00", "end_time": "08:30", "status": "available" }]
    }]
  }
}
```

As regras são as mesmas que a criação da reserva aplica, para que o livre
possa de fato ser reservado:

- **Ocupa a agenda** a reserva pendente ou aprovada; cancelada e recusada
  liberam o período (RN08). A diária (`start_time` e `end_time` nulos) ocupa o
  dia inteiro, e só cabe diária no dia sem nenhuma reserva.
- **A grade** vai da abertura ao fechamento em blocos de 30 minutos; se o
  fechamento não cair na grade, o último bloco é mais curto. Uma reserva fora
  da grade ocupa todo bloco que tocar.
- **Fica indisponível** o dia passado, o dia de área marcada como
  indisponível, a área sem horário de funcionamento cadastrado e o dia de hoje
  depois do último bloco. Hoje, os blocos que já começaram saem como `past` e
  não contam para o status do dia; a criação recusa o horário que já passou.
- **Os horários são comparados em minutos.** O MySQL devolve `TIME` como
  `HH:MM:SS` e a API recebe `HH:MM`; como texto, `"12:00" < "12:00:00"`, o
  que acusava conflito entre reservas encostadas (10h–12h e 12h–14h) e
  recusava reservar a partir da abertura. `App\Http\Utils\TimeUtil` resolve as
  duas pontas, e as respostas saem sempre em `HH:MM`.

Desempenho (RNF01), fixado no `CommonAreaAvailabilityPerformanceTest`: com 10
mil reservas em cinco áreas, a janela máxima de 62 dias responde em cerca de
10 ms, com 2 consultas — as mesmas com o dobro de reservas.

## Solicitação de reserva — UC03 / RF03 / RN01 / RN02

`POST /api/reservations`, só para quem tem `request-reservation` (o morador) e
está vinculado a uma unidade (`unit.linked`).

```json
{ "common_area_id": 3, "date": "2026-11-30", "start_time": "10:00", "end_time": "12:00" }
```

Sem `start_time` e `end_time` (ou com os dois nulos), é diária. A resposta
traz a reserva com `common_area` (id e nome) e uma mensagem que já diz o
desfecho: `"Reserva 12 da área 3 Quadra Poliesportiva para 30/11/2026, das
10:00 às 12:00, aprovada."` — ou `solicitada`, quando a área exige aprovação e
a reserva nasce pendente.

- **Quem reserva é a sessão.** Um `resident_id` no corpo é ignorado.
- **RN01** — sobreposição com reserva pendente ou aprovada responde **409**; a
  diária conflita com qualquer reserva do dia, nos dois sentidos. Reservas
  encostadas (10h–12h e 12h–14h) não conflitam. A área é travada
  (`lockForUpdate`) durante a checagem, para dois pedidos simultâneos não
  passarem juntos.
- **RN02** — respondem **422**: área indisponível; área sem horário de
  funcionamento cadastrado; data passada; horário de hoje que já começou;
  início antes da abertura ou fim depois do fechamento; horário fora da grade
  de 30 minutos contada a partir da abertura (terminar exatamente no
  fechamento vale, mesmo fora da grade); duração acima de
  `max_reservation_minutes`. Área sem limite usa **2 horas**, o mesmo padrão
  da tela de reservas — e a disponibilidade informa esse valor efetivo.
- A diária não tem limite de duração: ela é o dia.
- A criação e o log de auditoria (`TypeLogEnum::RESERVATION`) acontecem na
  mesma transação.

### Regras da área — RN02

> "Toda reserva deverá respeitar a disponibilidade, os horários permitidos e
> as regras de utilização definidas para a área comum."

`CheckReservationRulesAction` reúne o que a área define — é a irmã da
`CheckReservationConflictAction` (RN01). As duas rodam na criação **e de novo
na aprovação**, com a área como ela está no momento: se a administração
tornou a área indisponível, mudou o horário ou encurtou a duração enquanto o
pedido esperava, aprovar responde 422 com
`"Esta solicitação não pode mais ser aprovada: a área abre às 11:00. Reprove-a
informando o motivo."`. Reprovar não confere nada: é a saída para o pedido que
não cabe mais. A disponibilidade (`ListAvailabilityAction`) desenha o
calendário com as mesmas regras.

| Recusa | Campo em `errors` |
| --- | --- |
| área indisponível, área sem horário cadastrado | `common_area_id` |
| data passada | `date` |
| início antes da abertura, fora da grade, ou de hoje e já começado | `start_time` |
| fim depois do fechamento, fora da grade, antes do início ou acima da duração | `end_time` |
| só um dos dois horários | o que faltou |

Cada recusa do RN02 sai no formato de uma validação do Laravel, com a regra
na mensagem e o campo em `errors` — o RSV-04 do plano de testes pede as duas
coisas, e o front já lê `errors`:

```json
{ "status": false, "message": "A área abre às 08:00.", "code": 422,
  "error": "A área abre às 08:00.", "errors": { "start_time": ["A área abre às 08:00."] } }
```

O conflito (409) não leva `errors`: o formulário está certo, o horário é que
foi tomado.

A especificação não define outras regras de utilização além das que a área
guarda; limite de reservas por morador, antecedência mínima ou máxima e número
de convidados não existem no documento nem na tela, e não foram inventados.

### Cancelamento — RN08

> "O cancelamento de uma reserva deverá liberar novamente o respectivo período
> para novas reservas."

`POST /api/reservations/{id}/cancel`, sem corpo. O morador cancela as próprias
reservas (`cancel-own-reservation`), inclusive desistindo de um pedido ainda
pendente; a administração (`manage-reservations`), qualquer uma. A rota fica
fora do `unit.linked`: quem perdeu o vínculo com a unidade ainda pode desistir
das reservas que tinha.

```json
{
  "status": true,
  "message": "Reserva 12 da área 3 Quadra para 25/11/2026, das 10:00 às 12:00, cancelada. O período voltou a ficar disponível.",
  "data": { "id": 12, "status": "cancelled", "common_area": { "id": 3, "name": "Quadra" } }
}
```

- **A liberação é o próprio status**: só pendente e aprovada ocupam a agenda,
  então a reserva cancelada sai na hora da checagem de conflito e da
  disponibilidade — o mesmo período pode ser reservado em seguida.
- **Recusas**: reserva de outra pessoa (403); já cancelada, reprovada ou que
  já terminou (409) — data passada, ou hoje com o fim já alcançado. A que está
  em andamento ainda se cancela, como a tela de reservas permite, e a diária de
  hoje também.
- **Cancelar e aprovar ao mesmo tempo** termina sempre cancelada: as duas
  operações releem o status com trava antes de decidir, então a aprovação não
  grava "aprovada" por cima de um cancelamento (o que deixaria o período
  ocupado para sempre). Vale também para a tela que carregou o pedido antes de
  o morador desistir.
- Fica na auditoria como "cancelada", ou "cancelada pela administração" quando
  não foi o próprio morador.

### Consulta e análise — UC16 / UC17 / UC19

| Rota | Autorização | Efeito |
| --- | --- | --- |
| `GET /api/reservations/me` | `view-own-reservations` | UC17 — as reservas de quem está logado, e só dele |
| `GET /api/reservations` | `manage-reservations` ou `approve-or-reject-reservation` | UC19 — todas as reservas, com o morador (nome e unidade) |
| `GET /api/reservations/{id}` | qualquer um dos três | o detalhe; reserva de outra pessoa responde 404 |
| `PATCH /api/reservations/{id}/status` | `approve-or-reject-reservation` | UC16 — aprova ou reprova um pedido pendente |

- As listagens seguem o contrato das demais (`filters`, `sort`, `page`,
  `limit`), da data mais recente para a mais antiga, com até 100 por página
  sem `limit` — a tela de reservas carrega a lista numa requisição só. Cada
  linha traz `common_area` (id e nome), `status_label` e `full_day`.
- O parâmetro `relations` é descartado: pela listagem do morador sairia o
  cadastro inteiro de quem reservou. Os filtros do cliente se somam ao recorte
  da sessão — `filters=resident_id:=:<outro>` em `/me` devolve zero, não as
  reservas do vizinho.
- Horários saem sempre em `HH:MM`, e a reserva que termina à meia-noite sai com
  `end_time: "24:00"`, como a tela envia — o MySQL devolve `HH:MM:SS`.
- `PATCH .../status` recebe `{"status": "approved"}` ou
  `{"status": "rejected", "rejection_reason": "..."}`; o motivo é opcional,
  como na regra RN14 do frontend. A aprovação confere de novo as regras da
  área (RN02, 422 com `errors.<campo>`) e o conflito (RN01, 409); pedido que não
  está pendente responde 409. A decisão fica na auditoria
  (`reservation.approved` ou `reservation.rejected`), com o morador e o motivo.

### Conflito de reservas e pedidos simultâneos — RN01

`CheckReservationConflictAction` é a única porta: a criação e a aprovação
passam por ela, dentro da transação que grava. Dois pedidos para o mesmo
período chegando juntos leriam "livre" e gravariam os dois; por isso a
checagem:

1. **trava a linha da área** (`SELECT ... FOR UPDATE`): o segundo pedido espera
   o primeiro confirmar;
2. **lê as reservas com trava**, o que no InnoDB sempre enxerga o último dado
   confirmado. Uma leitura comum responderia com a foto que a transação tirou
   na primeira leitura — e a aprovação, que carrega a área antes de travar,
   veria a agenda antiga;
3. **compara `date` direto**, sem `whereDate()`, para o MySQL usar o índice
   `(common_area_id, date, status)` e travar só as linhas daquele dia.

O SQLite da suíte ignora `FOR UPDATE` e não é compartilhado entre processos,
então a disputa real fica no `ReservationRaceTest`, pulado na suíte comum. Ele
dispara oito processos PHP que largam no mesmo instante contra um MySQL:

```bash
# um MySQL vazio qualquer; o banco é recriado pelo próprio teste
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=condovale_race \
DB_USERNAME=root DB_PASSWORD= php artisan test --filter=ReservationRaceTest
```

Medido em 2026-11-24 com MySQL 8.0.44 (InnoDB, REPEATABLE-READ):

| Cenário | Sem trava | Só a trava da área | Como ficou |
| --- | --- | --- | --- |
| 8 pedidos do mesmo período | 8 gravados | 1 gravado, 7 × 409 | 1 gravado, 7 × 409 |
| 4 diárias + 4 períodos cruzados | 8 gravados | 1 gravado, 7 × 409 | 1 gravado, 7 × 409 |
| 4 períodos encostados | — | 4 gravados | 4 gravados |
| 6 pedidos cancelados e aprovados no mesmo instante | — | deadlock nos 6 | 6 cancelados |

A última linha é o cancelamento (RN08). Com o cancelamento travando só a
reserva, e a aprovação travando a área e depois as reservas do dia, as duas
pegavam as mesmas travas em ordens opostas e o MySQL respondia com deadlock
(1213). Agora **criar, aprovar e cancelar travam na mesma ordem — área, depois
reserva** — e as três transações são refeitas até
`Reservation::TRANSACTION_ATTEMPTS` (3) vezes se o InnoDB as escolher como
vítima de deadlock, que é o que o próprio MySQL manda fazer ("try restarting
transaction").

**Tudo isso depende de InnoDB**: em MyISAM não há transação nem trava de
linha. O `config/database.php` deixa o motor com o padrão do servidor. Para
conferir em produção, no phpMyAdmin do InfinityFree:

```sql
SELECT TABLE_SCHEMA, TABLE_NAME, ENGINE FROM information_schema.TABLES
WHERE TABLE_NAME IN ('common_areas', 'reservations');
```

Sem `DATABASE()`: no phpMyAdmin a aba SQL do servidor roda sem banco
selecionado, e aí `DATABASE()` é nulo e a consulta volta vazia. Se mesmo esta
voltar vazia, as tabelas não existem — faltam as migrations.

A suíte inteira ainda não roda contra MySQL: `DemoSeedTest` e
`DeployRouteTest` executam migrations por dentro, o DDL confirma a transação
do `RefreshDatabase` e os testes seguintes encontram dados de sobra. Os testes
de carga (`*PerformanceTest`) gravam chaves estrangeiras supondo ids a partir
de 1, o que só vale no SQLite, onde a sequência volta com o rollback. Os
demais testes de reservas, áreas, relatórios e painel passam contra MySQL. E o
`down()` da
migration de `occurrence_types` não roda no MySQL (erro 1553: o índice é usado
por uma chave estrangeira), o que impede `migrate:rollback` ali.

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

A suíte roda num processo só, e `tests/TestCase.php` recolhe o lixo de
memória ao fim de cada teste. Sem isso, o envio de imagem — que mede a memória
livre antes de redimensionar — passava a recusar as fotos dependendo de
quantos testes tinham rodado antes.

## O que mudou em relação ao import do InfinityFree

- `composer.json` apontava para `/usr/local/bin/php` e para um `cache-dir`
  absoluto do host; os scripts agora usam `@php` e rodam em qualquer máquina.
- `.env` continha o MySQL do InfinityFree; virou configuração local. As
  credenciais de produção passaram para `.env.production`, ignorado pelo git
  junto com qualquer outro `.env` — só o `.env.example` é versionado.
- `index2.html` e o arquivo-marcador "files for your website..." foram
  removidos, e o `.htaccess` da raiz deixou de redirecionar para
  `condoval.wuaze.com`.

## Risco aceito: CVE-2026-48019

O projeto **permanece no Laravel 11** por decisão de escopo. A versão instalada
é a 11.57.0, topo da linha, e quatro advisories do framework não têm correção
nela — todos exigem a 12 ou posterior. O `composer update` dentro do 11 já
resolveu os de `guzzle`, `commonmark` e `flysystem`.

O mais grave é o **CRLF injection na validação de e-mail** (CVE-2026-48019,
CVSS 8.9). Vale entender o mecanismo antes de dimensionar o risco:

- a falha **não** é aceitar quebra de linha literal — conferido na versão
  instalada, `email`, `email:rfc`, `email:strict` e `email:filter` recusam
  todas as cargas com `\r\n`;
- ela está em sequências que **passam** pela validação e que o Symfony Mailer
  reinterpreta como CRLF ao montar a mensagem, injetando cabeçalhos;
- portanto o alvo é o **envio**, não a entrada. O proveito do atacante é
  forjar uma mensagem que parece vir da aplicação.

**Hoje o CondoVale não envia e-mail nenhum.** `MAIL_MAILER=log` nos dois
ambientes, e não existe recuperação de senha, verificação de conta nem
notificação de comunicado. Sem transporte de saída, o caminho de exploração não
existe — é isso, e não a validação, que mantém o risco em zero.

A premissa cai no dia em que alguém configurar um transporte de verdade, e esse
dia chega junto com a recuperação de senha. Por isso o
`MailTransportRiskTest` é um fio de alarme: enquanto o framework estiver abaixo
da 12.60, ele falha se `mail.default` deixar de ser `log`, `array` ou `null`, e
a mensagem de falha explica o porquê. Quando o framework for atualizado, o
teste se marca como pulado e pode ser removido.

**Antes de implementar envio de e-mail, atualize o framework.**

## Pendências conhecidas

- O cancelamento de ocorrência está previsto para o administrador enquanto o
  atendimento não começou; a especificação não define quem pode cancelar.
- Reabertura de ocorrência concluída e reatribuição de atendimento já iniciado
  não são permitidas — ambas dependem de decisão registrada.
- `RNF01` (consultas abaixo de 2 s) ainda não tem carga padrão nem medição.
