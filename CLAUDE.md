# CLAUDE.md

Este arquivo orienta o Claude Code (claude.ai/code) neste repositório.

> **Como usar este arquivo:** ele descreve o padrão arquitetural que este projeto **deve** seguir, herdado de um sistema Laravel em produção. Substitua ou complemente o `CLAUDE.md` gerado pelo `/init` com este conteúdo. As seções com código não são sugestões: são as implementações a serem criadas no projeto novo, de forma que qualquer pessoa que já conheça o sistema irmão encontre tudo no mesmo lugar e com o mesmo nome.

---

## Idioma

Converse com o usuário em **português do Brasil**.

- **Em inglês:** nomes de classes, métodos, variáveis, colunas, arquivos e **comentários de código**.
- **Em português:** tudo que o usuário final lê — mensagens de validação, respostas da API, descrições de log, rótulos.

Essa divisão não é estética. Os identificadores em inglês mantêm o código alinhado com o framework e as bibliotecas; as strings em português fazem parte do produto e são lidas por servidores públicos e cidadãos.

---

## Arquitetura em camadas

O ponto central do padrão: **controllers são finos**. Eles validam, delegam e formatam a resposta. Não existe um *service* por model — em vez disso, a regra de negócio mora em **Actions** reutilizáveis.

```
app/
├── Http/
│   ├── Controllers/     # finos: validam, chamam Actions, respondem via MessageService
│   ├── Requests/        # toda validação, um par {Model}{Store,Update}Request por recurso
│   ├── Actions/         # a regra de negócio, métodos estáticos execute()
│   ├── Middleware/      # autorização e guardas de rota
│   └── Utils/           # helpers sem estado (SanitizeUtil, DateUtil, UserUtil...)
├── Services/            # SOMENTE questões transversais (MessageService, integrações externas)
├── Repositories/        # uso pontual, quando uma consulta é reaproveitada em vários pontos
└── Models/
```

### Actions

Uma Action encapsula **uma operação de negócio** e expõe um método estático `execute()`. Elas são chamadas de controllers, de outras Actions e de comandos de console.

```php
namespace App\Http\Actions;

class CalcFamilyIncomeAction
{
    /**
     * Calculates the per capita family income for an enrollment.
     */
    public static function execute(int $inscriptionId): float
    {
        // ...
    }
}
```

Regras:

- Uma Action por operação, nomeada pelo verbo: `CreateLogAction`, `UploadFileAction`, `ApprovalAction`, `SendEmailAction`.
- Actions do mesmo namespace se chamam sem `use` — `App\Http\Actions` já é o namespace corrente.
- Se a Action tiver variações, use métodos estáticos adicionais (`ApprovalAction::execute()`, `::checkApprove()`, `::fail()`), não subclasses.

### Services

Reserve `app/Services/` para o que **atravessa** a aplicação inteira: o envelope de resposta, licenciamento, integrações com sistemas externos. Se você está prestes a criar `UserService` para a regra de usuário, ela pertence a uma Action.

### Utils

Helpers estáticos sem estado e sem dependência de banco: sanitização, formatação de datas, manipulação de CPF. Nunca consultam o banco.

---

## MessageService — o envelope de resposta

**Todo endpoint responde pelo `MessageService`. Nunca use `response()->json()` direto num controller.** É isso que garante que o front-end tenha um contrato único para tratar sucesso, erro e paginação.

Crie exatamente este arquivo:

```php
<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class MessageService
{
    /**
     * Method that returns the formatted error
     */
    public static function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['status' => false, 'error' => $message], $status);
    }

    /**
     * Method that returns the formatted success
     */
    public static function success(string $message, mixed $model = [], bool $pagination = false): JsonResponse
    {
        $infos = ['status' => true];

        if ($pagination) {
            $infos['amount'] = count($model);
            $infos['total']  = $model->total();
            $infos['data']   = $model->items();
        } elseif ($model) {
            $infos['message'] = $message;
            $infos['data']    = $model;
        }

        return response()->json($infos, 201);
    }

    public static function throwable(\Throwable $th): JsonResponse
    {
        $statusCode = ((int) $th->getCode() >= 400 && (int) $th->getCode() < 600) ? (int) $th->getCode() : 500;
        $message = $statusCode === 500 ? 'Erro ao salvar as informações na base de dados.' : $th->getMessage();

        return response()->json([
            'status'  => false,
            'message' => $message,
            'code'    => $statusCode,
            'error'   => $th->getMessage(),
        ], $statusCode);
    }
}
```

### Como usar cada método

| Situação | Chamada | Corpo da resposta |
|---|---|---|
| Listagem paginada | `MessageService::success($msg, $paginator, true)` | `{status, amount, total, data}` |
| Registro único | `MessageService::success($msg, $model)` | `{status, message, data}` |
| Operação sem retorno (delete) | `MessageService::success($msg)` | `{status}` |
| Regra de negócio violada | `MessageService::error($msg)` → 400 | `{status, error}` |
| Não encontrado | `MessageService::error($msg, 404)` | `{status, error}` |
| Sem permissão | `MessageService::error($msg, 403)` | `{status, error}` |
| Exceção inesperada | `MessageService::throwable($th)` dentro do `catch` | `{status, message, code, error}` |

### Três comportamentos que você precisa conhecer

1. **`success()` devolve HTTP 201 em todos os casos**, inclusive em `GET`. É proposital, para manter o contrato idêntico ao do sistema irmão — o front-end trata `status: true`, não o código HTTP. Não "conserte" isso sem alinhar com o front-end antes.

2. **Quando `$model` é vazio e `$pagination` é `false`**, nenhum dos ramos executa e a resposta sai só com `{status: true}`, sem `message`. Se o endpoint precisa sempre devolver a mensagem, passe algo em `$model`.

3. **`throwable()` esconde o detalhe dos erros 5xx** atrás de uma mensagem genérica de banco, para não vazar estrutura de tabelas numa exceção. Erros com código HTTP válido (4xx) passam a mensagem original adiante.

> **Inconsistência conhecida:** `error()` devolve a chave `error`, enquanto `throwable()` devolve `message` + `code` + `error`. O sistema original convive com isso e o front-end trata os dois formatos. Se este projeto for novo e sem front-end legado, **unifique os dois formatos desde o início** — mas decida antes de escrever o primeiro controller, nunca no meio do caminho.

---

## HandlePaginationAction — o contrato genérico de consulta

Esta Action monta a query de praticamente todo `index()`. Ela recebe a request, o model e as colunas pesquisáveis, e devolve um `Builder` pronto para `->paginate()`.

O ponto crítico de segurança: **ela valida cada nome de coluna vindo do usuário contra o schema real da tabela, menos os `$hidden` do model**. É por isso que ela é o único lugar seguro para aceitar nome de coluna via query string.

```php
<?php

namespace App\Http\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HandlePaginationAction
{
    /**
     * Oracle rejects IN lists longer than this many entries (ORA-01795).
     */
    private const ORACLE_IN_CLAUSE_LIMIT = 999;

    /**
     * How long the table column listing stays cached.
     */
    private const COLUMN_CACHE_TTL_SECONDS = 86400;

    /**
     * Deepest relation chain a client may request through `relations`.
     */
    private const MAX_RELATION_DEPTH = 3;

    public static function execute(Request $request, Model $model, array $searchColumns = []): Builder
    {
        $tableName = $model->getTable();

        // Cached because the schema only changes on migrations.
        $tableColumns = Cache::remember(
            "table-columns:{$tableName}",
            self::COLUMN_CACHE_TTL_SECONDS,
            fn () => Schema::getColumnListing($tableName)
        );

        $safeColumns = array_diff($tableColumns, $model->getHidden());

        $attributes = $request->get('attributes') ?? '*';
        $selectColumns = $safeColumns;

        if ($attributes !== '*') {
            $requested = array_intersect(array_map('trim', explode(',', $attributes)), $safeColumns);

            if (! empty($requested)) {
                $selectColumns = $requested;
            }
        }

        $response = $model::select($selectColumns);

        $isSafeColumn = fn ($col) => in_array($col, $safeColumns);

        if ($id = $request->get('id')) {
            $response->where('id', $id);
        }

        if ($search = $request->get('search')) {
            $searchUpper = strtoupper($search);

            $response->where(function ($query) use ($searchColumns, $searchUpper, $isSafeColumn) {
                foreach ($searchColumns as $column) {
                    if ($isSafeColumn($column)) {
                        $query->orWhereRaw("UPPER({$column}) LIKE ?", ["%{$searchUpper}%"]);
                    }
                }
            });
        }

        if ($filters = $request->get('filters')) {
            foreach (explode(';', $filters) as $filter) {
                if (trim($filter) === '') {
                    continue;
                }

                $conditions = explode(':', $filter);

                if (count($conditions) === 3) {
                    [$column, $operator, $value] = array_map('trim', $conditions);

                    if ($value !== '' && $isSafeColumn($column)) {
                        $response->where($column, $operator, $value);
                    }
                }
            }
        }

        if ($filtersOr = $request->get('filtersOr')) {
            $allowedOperators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE'];

            // The whole OR block is wrapped, otherwise it would leak out and
            // cancel the tenancy where() the controller adds afterwards.
            $response->where(function ($query) use ($filtersOr, $allowedOperators, $isSafeColumn) {
                foreach (explode(';', $filtersOr) as $filter) {
                    $conditions = explode(':', $filter);

                    if (count($conditions) === 3) {
                        $column = trim($conditions[0]);
                        $operator = strtoupper(trim($conditions[1]));
                        $value = trim($conditions[2]);

                        if ($isSafeColumn($column) && in_array($operator, $allowedOperators)) {
                            $query->orWhereRaw("UPPER({$column}) {$operator} ?", [strtoupper($value)]);
                        }
                    }
                }
            });
        }

        if ($filtersIn = $request->get('filtersIn')) {
            foreach (explode(';', $filtersIn) as $filter) {
                $conditions = explode(':', $filter);

                if (count($conditions) >= 2) {
                    $column = trim($conditions[0]);

                    if ($isSafeColumn($column)) {
                        // Split into OR'ed chunks so a long list cannot break the query.
                        $chunks = array_chunk(explode(',', $conditions[1]), self::ORACLE_IN_CLAUSE_LIMIT);

                        $response->where(function ($query) use ($column, $chunks) {
                            foreach ($chunks as $chunk) {
                                $query->orWhereIn($column, $chunk);
                            }
                        });
                    }
                }
            }
        }

        $direction = strtolower($request->get('direction') ?? 'asc');
        $direction = in_array($direction, ['asc', 'desc']) ? $direction : 'asc';

        if (($sort = $request->get('sort')) && $isSafeColumn($sort)) {
            $response->orderBy($sort, $direction);
        }

        // Only relations declared on the model are accepted, and nesting is capped
        // so a single request cannot cascade through the whole domain.
        if ($relations = $request->get('relations')) {
            foreach (explode(';', $relations) as $relation) {
                $relation = trim($relation);

                if ($relation === '' || count(explode('.', $relation)) > self::MAX_RELATION_DEPTH) {
                    continue;
                }

                $relationName = explode(':', explode('.', $relation)[0])[0];

                if (method_exists($model, $relationName)) {
                    $response->with($relation);
                }
            }
        }

        return $response;
    }
}
```

### Parâmetros aceitos na query string

| Parâmetro | Formato | Efeito |
|---|---|---|
| `attributes` | `col1,col2` | `SELECT` restrito |
| `id` | inteiro | filtro direto por id |
| `search` | texto | `OR` case-insensitive sobre as `$searchColumns` |
| `filters` | `col:op:val;col:op:val` | `AND` encadeado |
| `filtersOr` | `col:op:val;…` | `OR` dentro de um bloco isolado |
| `filtersIn` | `col:v1,v2;…` | `WHERE IN`, fatiado |
| `sort` + `direction` | coluna + `asc`/`desc` | ordenação |
| `relations` | `rel1;rel2.sub` | eager load, até 3 níveis |
| `limit`, `page` | inteiros | consumidos pelo `->paginate()` de quem chamou |

### Duas obrigações que decorrem disso

1. **Todo model precisa declarar `$hidden`** com as colunas que não podem sair numa listagem — senha, token, e também dado pessoal sensível como CPF, RG, filiação e endereço, quando a listagem for administrativa. A Action só protege o que o model declarar.
2. **O `where()` de tenancy vem depois**, no controller: `HandlePaginationAction::execute(...)` e então `->where('institution_id', $institutionId)` antes de paginar.

---

## CreateLogAction — a trilha de auditoria

**Toda mutação registra log por esta Action. Nunca use o facade `Illuminate\Support\Facades\Log` para registrar ação de usuário.** O facade escreve em arquivo; esta Action grava na tabela `logs`, que é o que a auditoria do sistema consulta.

```php
<?php

namespace App\Http\Actions;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class CreateLogAction
{
    /**
     * Stores the log entry in the database.
     */
    public static function execute(int $typeLogId, string $description, array|object|null $dataLog = null): void
    {
        $userId = Auth::check() ? (Auth::id() ?? 1) : 1;

        $attributes = [
            'type_log_id' => $typeLogId,
            'user_id'     => $userId,
            'description' => $description,
            'ip'          => Request::ip(),
        ];

        if ($dataLog) {
            $attributes['data_log'] = json_encode($dataLog);
        }

        Log::create($attributes);
    }
}
```

Regras:

- Use o **facade `Auth`**, não o helper `auth()`. O helper é tipado como um contrato que não declara todos os métodos, o que gera "undefined method" no editor.
- A `$description` é em **português**, no passado, e cita o id e o nome do registro: `"Tipo de benefício {$id} {$name} criado."`
- Chame **depois** da escrita e **dentro** da transação, para que um rollback também descarte o log.

### Tipos de log

Os `type_log_id` são ids de uma tabela de referência semeada por JSON. Evite espalhar números mágicos: **crie um enum** desde o começo do projeto e use-o em todas as chamadas.

```php
namespace App\Enums;

enum TypeLogEnum: int
{
    case LOGIN = 2;
    case EMAIL = 14;
    // ...
}

CreateLogAction::execute(TypeLogEnum::LOGIN->value, $description);
```

O sistema irmão nasceu sem isso e acumulou centenas de ids soltos no código. Não repita.

---

## SanitizeUtil — todo id recebido passa por aqui

```php
<?php

namespace App\Http\Utils;

class SanitizeUtil
{
    public static function sanitizeString($string): string
    {
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeInt($int): int
    {
        // FILTER_SANITIZE_NUMBER_INT returns a string and keeps "+" and "-",
        // so the cast is what actually guarantees an integer id.
        return (int) filter_var($int, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function sanitizeEmail($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}
```

**Regra:** todo id vindo de rota ou query string passa por `SanitizeUtil::sanitizeInt()` antes de qualquer consulta. Todo texto livre vindo de rota passa por `sanitizeString()`.

---

## Form Requests — toda validação

Um par `{Model}StoreRequest` / `{Model}UpdateRequest` por recurso. Regras em formato de **string**, não array.

```php
public function rules(): array
{
    return [
        'name'        => 'required|string|max:100',
        'active'      => 'sometimes|boolean',
        'program_id'  => 'required|integer|exists:programs,id',
    ];
}
```

- `authorize()` devolve `true`: a autorização é resolvida pelo grupo de rota e pelo middleware, não pela Request.
- Mensagens de validação customizadas ficam em `messages()`, **em português**.
- Para listagens, crie `DefaultPaginationRequest` e faça as demais herdarem dela:

```php
class DefaultPaginationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attributes' => 'nullable|string',
            'direction'  => 'nullable|string',
            'search'     => 'nullable|string',
            'limit'      => 'nullable|integer',
            'page'       => 'nullable|integer',
            'sort'       => 'nullable|string',
        ];
    }
}
```

---

## O controller canônico

Este é o formato de referência. Um CRUD novo deve sair exatamente assim:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Actions\CreateLogAction;
use App\Http\Actions\HandlePaginationAction;
use App\Http\Requests\BenefitTypeStoreRequest;
use App\Http\Requests\BenefitTypeUpdateRequest;
use App\Http\Requests\DefaultPaginationRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\BenefitType;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

class BenefitTypeController extends Controller
{
    public function index(DefaultPaginationRequest $request, BenefitType $benefitType)
    {
        $item = (object) $request->validated();
        $searchColumns = ['name'];

        $response = HandlePaginationAction::execute($request, $benefitType, $searchColumns);
        $benefitTypes = $response->paginate($item->limit ?? 10);

        return MessageService::success('Tipos de benefício retornados.', $benefitTypes, true);
    }

    public function store(BenefitTypeStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $attributes = $request->validated();
            $benefitType = BenefitType::create($attributes);

            $description = "Tipo de benefício {$benefitType->id} {$benefitType->name} criado.";
            CreateLogAction::execute(TypeLogEnum::BENEFIT_TYPE->value, $description, $benefitType);

            DB::commit();

            return MessageService::success($description, $benefitType);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function show(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $benefitType = BenefitType::find($id);

        if (! $benefitType) {
            return MessageService::error("Tipo de benefício {$id} não encontrado.", 404);
        }

        return MessageService::success("Tipo de benefício {$benefitType->id} encontrado.", $benefitType);
    }

    public function update(BenefitTypeUpdateRequest $request, int $id)
    {
        $attributes = $request->validated();
        $id = SanitizeUtil::sanitizeInt($id);
        $benefitType = BenefitType::find($id);

        if (! $benefitType) {
            return MessageService::error("Tipo de benefício com o código {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $benefitType->update($attributes);

            $description = "Tipo de benefício {$benefitType->id} {$benefitType->name} editado.";
            CreateLogAction::execute(TypeLogEnum::BENEFIT_TYPE->value, $description, (object) $attributes);

            DB::commit();

            return MessageService::success($description, $benefitType);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }

    public function destroy(int $id)
    {
        $id = SanitizeUtil::sanitizeInt($id);
        $benefitType = BenefitType::find($id);

        if (! $benefitType) {
            return MessageService::error("Tipo de benefício com o código {$id} não encontrado.", 404);
        }

        try {
            DB::beginTransaction();

            $benefitType->delete();

            $description = "Tipo de benefício {$benefitType->id} excluído.";
            CreateLogAction::execute(TypeLogEnum::BENEFIT_TYPE->value, $description);

            DB::commit();

            return MessageService::success($description);
        } catch (\Throwable $th) {
            DB::rollBack();

            return MessageService::throwable($th);
        }
    }
}
```

### Transações

Toda ação com **mais de uma escrita** — ou com escrita + log — abre transação:

```php
try {
    DB::beginTransaction();
    // ... escritas ...
    DB::commit();
} catch (\Throwable $th) {
    DB::rollBack();
    return MessageService::throwable($th);
}
```

**Armadilha frequente:** se houver `return` antecipado por regra de negócio **depois** do `beginTransaction()`, ele precisa dar `DB::rollBack()` antes de retornar. Transação aberta e abandonada trava conexão. Prefira validar tudo **antes** de abrir a transação.

---

## Rotas e autorização

O arquivo `routes/api.php` é organizado em **grupos**, e o grupo em que a rota está *é* o seu modelo de autorização. Nunca espalhe verificação de permissão dentro do controller.

| Prefixo | Middleware | Significado |
|---|---|---|
| `/public` | guarda de origem | consultas e inscrições sem autenticação |
| `/auth/login` | — | autenticação |
| `/v1` | `auth:api` + usuário ativo + senha trocada | usuário logado, sem exigir permissão de menu |
| `/v1` | `+ CheckPermissionMenu` | área administrativa |
| `/v1/external` | `auth:sanctum`, `throttle`, `abilities:*` | API máquina a máquina |

### Permissão de menu é dado, não código

O middleware de permissão resolve a string `Controller@method` da rota atual e a procura numa tabela `pages`, coluna `page_path`. Consequência prática, e a causa mais comum de "a rota nova dá 403":

> **Uma rota administrativa nova responde 403/404 até existir a linha correspondente na tabela `pages`.** Ao criar um endpoint administrativo, adicione a entrada no seeder JSON e crie os vínculos com os papéis que podem acessá-la.

Consequência menos óbvia: **renomear um controller quebra a autorização em produção** até o seeder ser rodado de novo. Se renomear, atualize o `page_path` no JSON e avise a infraestrutura.

### Middleware de acesso

Registre os aliases em `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'user.active'     => \App\Http\Middleware\EnsureUserIsActive::class,
        'password.change' => \App\Http\Middleware\ForcePasswordChange::class,
    ]);
})
```

**Verificar `active` no login não basta.** Um token emitido antes da desativação continua valendo até expirar. É preciso um middleware por requisição que rejeite usuário inativo — esse foi um furo real encontrado no sistema irmão.

---

## Autenticação

O padrão usa **dois sistemas na mesma aplicação**:

- **JWT** (`tymon/jwt-auth`) no guard `api`, para pessoas.
- **Personal access tokens do Sanctum** em `/v1/external`, para integrações máquina a máquina.

Na API externa, o tenant vem **do token**, nunca do corpo da requisição:

```php
$institutionId = SanitizeUtil::sanitizeInt(
    $request->user()->currentAccessToken()->institution_id
);

$courses = Course::where('institution_id', $institutionId)->get();
```

### AuthUtil — acessores tipados do guard

`auth('api')` é tipado como o contrato `Guard|StatefulGuard`, que **não declara** `refresh()` nem `payload()`, e cujo `user()` devolve `Authenticatable` em vez do model. Isso gera falsos "undefined method" no editor e esconde erros reais. Crie:

```php
<?php

namespace App\Http\Utils;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\JWTGuard;

class AuthUtil
{
    public static function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        return $guard;
    }

    public static function user(): ?User
    {
        /** @var User|null $user */
        $user = self::guard()->user();

        return $user;
    }
}
```

E sempre informe o guard explicitamente. `auth()->refresh()` sem guard cai no guard padrão do `.env`; se a variável faltar em produção, vira erro fatal.

---

## Rate limiting: nunca por IP

**Regra firme deste padrão:** limites são chaveados pela **conta**, jamais pelo endereço IP.

O motivo é o cenário de uso. As instituições acessam o sistema pelo wi-fi do campus, que sai para a internet com um único IP público por NAT. Um limite por IP colocaria todos os servidores e alunos da instituição no mesmo balde: uma pessoa errando a senha bloquearia o prédio inteiro. Pior ainda quando há proxy na frente do PHP sem `TrustProxies` configurado — aí `$request->ip()` devolve o IP do proxy para **todas** as requisições e o limite vale para o sistema inteiro de uma vez.

```php
// app/Providers/AppServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    $key = Str::lower(trim((string) $request->input('field')) . '|' . trim((string) $request->input('login')));

    return Limit::perMinute(5)
        ->by('login:' . $key)
        ->response(fn (Request $request, array $headers) => response()->json([
            'message' => 'Muitas tentativas de login para esta conta. Aguarde um minuto e tente novamente.',
            'code'    => 'TOO_MANY_ATTEMPTS',
        ], 429, $headers));
});
```

Na rota, use o **limitador nomeado**:

```php
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
```

`throttle:login` usa a chave definida no closure. É a forma `throttle:5,1`, sem limitador nomeado, que recai sobre o IP — essa **não** deve ser usada.

O mesmo vale para recuperação de senha, chaveada pelo e-mail: cada chamada dispara e-mail e invalida o token anterior.

---

## Configuração: `config()`, nunca `env()` em runtime

`env()` só funciona enquanto não houver cache de configuração. Depois de `php artisan config:cache` — que é o normal em produção — **`env()` devolve `null` silenciosamente**. Nada quebra com erro: a integração simplesmente passa a enviar credencial vazia.

Portanto:

- `env()` aparece **somente** dentro de `config/*.php`.
- No resto da aplicação, sempre `config('services.x.y')`.

```php
// config/services.php
'univali_email' => [
    'url_prod' => env('API_URL_EMAIL_PROD'),
    'hash'     => env('API_HASH_EMAIL'),
    'timeout'  => env('API_EMAIL_TIMEOUT', 10),
],
```

```php
// no serviço
$hash = config('services.univali_email.hash');

if (! $hash) {
    throw new \RuntimeException('Hash da integração de e-mail não configurado.');
}
```

Para valores obrigatórios, **não use fallback**: lance exceção. Um fallback silencioso transforma erro de configuração em bug de produção difícil de achar.

---

## Integrações externas

Ficam em `app/Services/`, com timeout e retry explícitos:

```php
$response = Http::timeout(config('services.x.timeout'))
    ->retry(config('services.x.retry_times'), config('services.x.retry_delay'), throw: false)
    ->post($url, $payload);
```

- Sempre `timeout()`. Sem ele, uma integração lenta trava o worker.
- `throw: false` para tratar a falha, registrar log e devolver mensagem em português.
- **Nunca registre o corpo da requisição no log** quando ele puder conter senha temporária, token ou dado pessoal. Registre status, endpoint e identificador.
- Ao relançar exceção, use `throw $th`, não `throw new $th` — este último perde a mensagem original.

---

## Seeders: um JSON por tabela

Este é um dos pontos em que o padrão **se afasta do Laravel convencional**, e ele precisa ser seguido à risca. Não se usa `factory()` nem array embutido no seeder: os dados ficam num **arquivo JSON por tabela**, e o seeder apenas lê e mapeia.

O motivo é operacional. As tabelas de referência — status, tipos, papéis, páginas, textos padrão — são **configuração do produto**, não dado de teste. Mantê-las em JSON versionado permite revisar uma mudança de conteúdo no diff do merge request, sem ler PHP, e permite reaplicar a carga em produção sem migration nova.

### Estrutura

```
database/
├── seeders/
│   ├── DatabaseSeeder.php        # chama todos, na ordem de dependência
│   ├── BenefitTypeSeeder.php     # um seeder por tabela
│   ├── TypeLogSeeder.php
│   ├── PageSeeder.php
│   └── json/
│       ├── benefit_types.json    # um JSON por tabela, nome = nome da tabela
│       ├── type_logs.json
│       └── pages.json
```

O nome do JSON é o **nome da tabela** em snake_case plural. O nome do seeder é o **nome do model** + `Seeder`. Essa correspondência é o que torna o conjunto navegável com 70+ tabelas.

### Anatomia do seeder

```php
<?php

namespace Database\Seeders;

use App\Models\BenefitType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BenefitTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('seeders/json/benefit_types.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'name'        => $item->name,
                'description' => $item->description,
                'active'      => $item->active,
            ];

            BenefitType::updateOrCreate(['name' => $item->name], $array);
        }
    }
}
```

```json
[
    {
        "id": 1,
        "name": "Benefício",
        "description": "Benefício",
        "active": 1
    }
]
```

Quatro detalhes que fazem parte do padrão:

1. **`File::get(database_path('seeders/json/...'))`** — sempre o facade `File` e sempre `database_path()`, nunca caminho relativo.
2. **`json_decode($json)` sem o segundo argumento** — o JSON vira **objeto**, e o acesso é `$item->name`. Não use `true` para virar array associativo; o restante dos seeders não faz isso e a inconsistência atrapalha na hora de copiar um seeder existente.
3. **Mapeamento explícito, coluna por coluna**, numa variável `$array`. Nunca passe `$item` direto para o `create()`. O mapa explícito é o que impede que uma chave nova no JSON entre no banco sem alguém ter decidido isso, e é onde se colocam valores fixos (`'active' => true`, `'responsible_id' => 1`).
4. **Uma tabela por seeder.** Se dois models precisam ser semeados juntos, são dois seeders chamados em sequência.

### As três variantes — e qual usar

Esta é a parte que mais importa, porque a escolha errada quebra produção.

**1. `updateOrCreate` por chave de negócio — use esta por padrão.**

```php
Page::updateOrCreate(['code' => $item->code], $array);
```

É idempotente: pode ser reexecutada em produção para aplicar uma alteração de conteúdo sem duplicar nada. Toda tabela de referência que possa mudar depois do primeiro deploy deve usar esta forma, chaveada por um campo estável de negócio (`code`, `acronym`), **nunca pelo `id`**.

Quando a chave é composta, ela vai inteira no primeiro argumento:

```php
PagePaper::updateOrCreate(
    ['paper_id' => $item->paper_id, 'page_id' => $item->page_id],
    ['responsible_id' => $item->responsible_id ?? 1, 'active' => true]
);
```

**2. `create` com `id` fixo — para tabela cujos ids aparecem no código.**

```php
TypeLog::create([
    'id'          => $item->id,
    'name'        => $item->name,
    'description' => $item->description,
]);
```

Se o código em algum lugar escreve o id literal — `CreateLogAction::execute(14, ...)`, `DefaultText::find(42)` —, então **esse id é contrato** e precisa ser fixado pelo JSON, não deixado a cargo da sequência do banco. Caso contrário, a ordem de inserção passa a definir o comportamento do sistema, e uma linha inserida no meio do JSON desloca tudo.

Melhor ainda: fixe o id **e** crie um enum para nunca escrever o número no código. Veja a seção do `CreateLogAction`.

**3. `create` puro, com id gerado — evite.**

```php
BenefitType::create($array); // duplica a cada reexecução
```

É o que alguns seeders do sistema irmão fazem, e é um débito, não um exemplo. Rodar `db:seed` duas vezes duplica as linhas. Só é aceitável para dado de desenvolvimento descartável.

### Resolver chave estrangeira por chave de negócio

Quando o JSON referencia outra tabela, prefira gravar a **chave de negócio** e resolver o id na hora de semear. Assim o JSON não depende dos ids que o banco gerou:

```php
if (empty($item->page_id)) {
    $item->page_id = Page::where('code', $item->code)?->pluck('id')?->first();

    if (! $item->page_id) {
        continue; // a página ainda não existe: pula em vez de estourar
    }
}
```

### DatabaseSeeder e a ordem de dependência

O `DatabaseSeeder` chama todos os seeders numa lista única, **na ordem em que as chaves estrangeiras exigem** — tabelas sem dependência primeiro, tabelas que referenciam outras depois:

```php
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            NationalitySeeder::class,
            CountrySeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            UserSeeder::class,
            TypeLogSeeder::class,
            PaperSeeder::class,
            PageSeeder::class,
            PagePaperSeeder::class,
            // ...
        ]);
    }
}
```

O `use WithoutModelEvents` é obrigatório: sem ele, observers e eventos de model disparam durante a carga e produzem efeito colateral (e-mail enviado, log gravado) a cada `db:seed`.

**Ao criar um seeder novo, acrescente-o nessa lista na posição certa.** Um seeder que não está no `DatabaseSeeder` simplesmente nunca roda, e o erro só aparece quando alguém monta o ambiente do zero.

### Geração automática

Existe um comando de console que varre as migrations e cria o que estiver faltando — Model, Seeder e o JSON vazio correspondente:

```bash
php artisan atualize:seeders
```

Use-o depois de criar migrations novas, e então preencha o JSON à mão. Ele não adivinha conteúdo, só monta o esqueleto no padrão certo.

### Regras práticas

- Precisa de uma linha nova numa tabela de referência? **Acrescente no JSON**, não crie migration avulsa com `DB::table()->insert()`.
- Seeders que rodam em produção precisam ser idempotentes (`updateOrCreate`). Seeders de dado de exemplo, que nunca rodam em produção, podem ser mais soltos — mas deixe isso explícito no nome ou num comentário no topo da classe.
- Alterou um `page_path` no `pages.json` por causa de renomeação de controller? **O seeder precisa rodar em produção**, ou a rota passa a responder 403.

---

## Banco de dados

- Se o alvo for **Oracle**: identificadores têm limite de **30 caracteres**, há *case folding*, e listas `IN` estouram em 1000 entradas (`ORA-01795`) — por isso o `array_chunk` no `HandlePaginationAction`.
- Tipos polimórficos são fixados por `Relation::enforceMorphMap()` no `AppServiceProvider`. Use os aliases, nunca o nome da classe, para que renomear um model não invalide os dados já gravados.

---

## Arquivos enviados

- Nunca em `public/`. Use um disco privado, fora da raiz web.
- Valide **extensão e tamanho contra a configuração do tipo de arquivo**, não só contra a regra genérica do Laravel.
- Download privado passa por rota autenticada com verificação de quem pode baixar aquele arquivo específico — dono, responsável ou avaliador. Um token na URL, sozinho, não é autorização.
- Ao substituir um arquivo, **só apague o antigo depois** que o novo estiver gravado e o registro criado, e confira que os caminhos são diferentes.

---

## Convenções gerais

- Use `php artisan make:*` para criar arquivos novos e mantenha os diretórios existentes.
- O nome do arquivo **precisa** bater com o nome da classe, senão o PSR-4 não carrega. Ao renomear uma classe, renomeie o arquivo na mesma hora e procure por chamadas ao nome antigo — inclusive em **seeders**, que passam despercebidos por não serem exercitados em desenvolvimento.
- Nada de import não utilizado. Rode a verificação antes de fechar uma alteração.
- Comentário explica **por quê**, não o quê. Comentário que narra a linha seguinte é ruído.
- Ao terminar qualquer alteração: `vendor/bin/pint --dirty`.

---

## Comandos

```bash
# Testes
php artisan test --compact
php artisan test --compact --filter=nomeDoTeste

# Formatação — rodar antes de finalizar qualquer alteração
vendor/bin/pint --dirty

# Filas e logs
php artisan queue:listen
php artisan pail
```

---

## Checklist antes de dar uma alteração por concluída

1. `php -l` passa em todos os arquivos tocados.
2. Nenhuma chamada a método ou classe renomeada ficou para trás — procure o nome antigo em `app/`, `routes/`, `database/` e `tests/`.
3. Todo id de rota passou por `SanitizeUtil::sanitizeInt()`.
4. Toda mutação gravou log por `CreateLogAction`.
5. Toda resposta saiu por `MessageService`.
6. Ação com múltiplas escritas está dentro de transação, e todo `return` antecipado dá `rollBack()`.
7. Rota administrativa nova tem a linha correspondente no seeder de páginas.
8. Model novo declara `$hidden`.
9. Tabela nova tem seeder + JSON, e o seeder foi acrescentado ao `DatabaseSeeder` na posição certa da ordem de dependência.
10. Linha nova em tabela de referência foi acrescentada no JSON, não por migration avulsa.
11. Seeder que roda em produção usa `updateOrCreate` chaveado por campo de negócio.
12. `vendor/bin/pint --dirty` rodou.
13. Nenhum `env()` fora de `config/`.
