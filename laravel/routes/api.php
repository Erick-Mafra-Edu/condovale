<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\OccurrenceAttachmentController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\OccurrenceTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitOccupancyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API do CondoVale
|--------------------------------------------------------------------------
|
| As rotas de cada módulo entram aqui junto com o controller da sua task.
|
| O grupo em que a rota está É o seu modelo de autorização: nenhum controller
| verifica permissão. Quem decide é o middleware use.case, alimentado pela
| matriz de papéis do diagrama de casos de uso (App\Enums\UserRole), e o
| user.active garante a RN04 desconectando quem for inativado durante a sessão.
|
| Forma esperada de registrar um módulo novo:
|
|     Route::middleware(['auth', 'user.active'])->group(function () {
|         Route::get('reservations', [ReservationController::class, 'index'])
|             ->middleware('use.case:manage-reservations');
|
|         Route::post('reservations', [ReservationController::class, 'store'])
|             ->middleware('use.case:request-reservation');
|     });
|
| O middleware use.case aceita vários casos de uso separados por vírgula e
| libera quando o papel do usuário possui qualquer um deles.
|
| A autenticação é por sessão em cookie HttpOnly: a identidade do autor de
| qualquer operação vem da sessão, nunca do corpo da requisição.
|
| O limitador de login é nomeado e chaveado pela conta, nunca pelo IP:
|
|     Route::post('auth/login', [AuthController::class, 'login'])
|         ->middleware('throttle:login');
|
*/

/*
|--------------------------------------------------------------------------
| Implantação remota
|--------------------------------------------------------------------------
|
| O plano gratuito do InfinityFree não tem SSH, então não existe onde rodar
| `php artisan migrate`. Esta rota faz a carga inicial pelo próprio HTTP.
|
| Ela é pública por necessidade — quem implanta ainda não tem usuário no banco,
| porque o banco é justamente o que está sendo criado —, então a proteção é o
| middleware deploy.token: sem DEPLOY_ENABLED=true e sem o token certo em
| X-Deploy-Token a rota responde 404.
|
| ATENÇÃO: derruba todas as tabelas. Só use na implantação.
|
| O throttle vem antes do token para que a tentativa em massa esbarre no
| limite antes de chegar à comparação. A chave é fixa, e não o IP: um balde
| único para o endpoint inteiro, coerente com a regra do projeto de nunca
| chavear limite por endereço.
*/
// Sem a sessão: ela é gravada no banco, e esta é justamente a rota que apaga e
// recria o banco. O middleware lê a tabela `sessions` antes do controller e a
// grava depois, então a requisição morreria nas duas pontas, deixando o schema
// pela metade. Autenticação por sessão aqui não faria sentido de todo jeito —
// quem chama é uma máquina, com token.
Route::post('deploy/migrate', [DeployController::class, 'migrate'])
    ->withoutMiddleware([StartSession::class])
    ->middleware(['throttle:deploy', 'deploy.token']);

// A hospedagem compartilhada não publica a configuração do PHP e não há SSH
// para inspecioná-la: esta rota é a única forma de saber, de dentro do próprio
// servidor, se o Argon2id exigido pela RNF02 está disponível lá.
Route::get('deploy/hashing', [DeployController::class, 'hashing'])
    ->withoutMiddleware([StartSession::class])
    ->middleware(['throttle:deploy', 'deploy.token']);

Route::get('deploy/mail', [DeployController::class, 'mail'])
    ->withoutMiddleware([StartSession::class])
    ->middleware(['throttle:deploy', 'deploy.token']);

// Os limites de upload do InfinityFree só são conhecidos por relato de fórum;
// esta rota diz, de dentro do servidor, o teto real, se há GD e quanto disco
// resta.
Route::get('deploy/uploads', [DeployController::class, 'uploads'])
    ->withoutMiddleware([StartSession::class])
    ->middleware(['throttle:deploy', 'deploy.token']);

// UC-T01 / RNF01 — o `php artisan optimize` que não há como rodar por SSH.
//
// Precisa ser chamada depois de cada publicação: o publicador apaga os caches
// de inicialização ao enviar arquivos, para que configuração velha nunca
// sobreviva a um deploy.
Route::post('deploy/optimize', [DeployController::class, 'optimize'])
    ->withoutMiddleware([StartSession::class])
    ->middleware(['throttle:deploy', 'deploy.token']);

/*
|--------------------------------------------------------------------------
| Autenticação — UC02 / RF02
|--------------------------------------------------------------------------
|
| As três rotas são públicas por natureza: quem chega ainda não tem sessão.
| A restrição de quem entra é a credencial, e a RN04 é aplicada dentro da
| AuthenticateUserAction, que recusa usuário inativo.
|
| O `user.active` também cobre as rotas de sessão e de saída: ele ignora
| visitante e, quando encontra um usuário inativado no meio da sessão, encerra
| a sessão ali mesmo.
|
| O limitador do login é nomeado e chaveado pela conta, nunca pelo IP: os
| moradores acessam pela mesma rede do condomínio, e um limite por endereço
| bloquearia o prédio inteiro por causa de uma pessoa errando a senha.
*/
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('user.active')->group(function () {
    Route::get('auth/session', [AuthController::class, 'session']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| Cadastro próprio — UC01 / RF01
|--------------------------------------------------------------------------
|
| Aqui cada um mexe apenas em si: o id vem da sessão, nunca da requisição, de
| modo que não existe como editar o cadastro alheio por esta porta. O caso de
| uso update-own-profile pertence a todos os perfis.
|
| A troca de senha fica **fora** do password.change de propósito: ela é a saída
| do bloqueio do primeiro acesso. Pôr o middleware nela trancaria a pessoa do
| lado de fora da única rota capaz de destrancá-la.
*/
Route::middleware(['auth', 'user.active', 'use.case:update-own-profile'])->group(function () {
    Route::patch('users/me/password', [ProfileController::class, 'changePassword']);

    Route::middleware('password.change')->group(function () {
        Route::get('users/me', [ProfileController::class, 'show']);
        Route::patch('users/me', [ProfileController::class, 'update']);
    });
});

/*
|--------------------------------------------------------------------------
| Gestão de usuários — UC01 / RF01
|--------------------------------------------------------------------------
|
| manage-residents é o caso de uso do UC01 e pertence só ao administrador: o
| síndico responde por comunicados e relatórios, não pelo cadastro de pessoas.
|
| O PATCH edita qualquer dado, inclusive perfil e situação — a reativação é ele
| com `status: active`. Não há rota dedicada para trocar perfil: duas portas
| para a mesma mudança significam duas regras para manter em acordo.
*/
Route::middleware(['auth', 'user.active', 'password.change', 'use.case:manage-residents'])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::patch('users/{id}', [UserController::class, 'update']);
    Route::post('users/{id}/deactivate', [UserController::class, 'deactivate']);
});

/*
|--------------------------------------------------------------------------
| Perfis e permissões — UC01 / RF01
|--------------------------------------------------------------------------
|
| O administrador concede e revoga casos de uso por pessoa. A concessão
| individual se soma ao que o perfil já dá, o que permite abrir uma exceção
| para alguém sem promovê-la de perfil.
|
| manage-residents é o caso de uso do UC01 na matriz, e só o administrador o
| possui — é o grupo de rota que resolve isso, não os controllers.
*/
Route::middleware(['auth', 'user.active', 'password.change', 'use.case:manage-residents'])->group(function () {
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('roles', [PermissionController::class, 'roles']);

    Route::get('users/{id}/permissions', [UserPermissionController::class, 'index']);
    Route::post('users/{id}/permissions', [UserPermissionController::class, 'store']);
    Route::delete('users/{id}/permissions/{permission}', [UserPermissionController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Unidades — RN03
|--------------------------------------------------------------------------
|
| Alterações estruturais de unidades. O grupo de rota é quem resolve quem
| chega aqui, pelo caso de uso manage-units.
|
| O PATCH edita qualquer dado e a reativação é ele com `status: active`. A
| inativação tem rota própria porque é a operação que a administração procura
| pelo nome — e as duas compartilham a mesma regra, na Action.
*/
Route::middleware(['auth', 'user.active', 'password.change', 'use.case:manage-units'])->group(function () {
    Route::get('units', [UnitController::class, 'index']);
    Route::get('units/{id}', [UnitController::class, 'show']);
    Route::post('units', [UnitController::class, 'store']);
    Route::patch('units/{id}', [UnitController::class, 'update']);
    Route::post('units/{id}/deactivate', [UnitController::class, 'deactivate']);
});

/*
|--------------------------------------------------------------------------
| Vínculo entre morador e unidade
|--------------------------------------------------------------------------
|
| Origem: diagrama de sequência "Gerenciamento de Moradores e Unidades" e a
| associação Usuario-Unidade do diagrama de classes.
|
| O caso de uso link-residents-to-units pertence só ao administrador, então é
| o grupo de rota que resolve a autorização — nenhum controller verifica
| permissão.
*/
Route::middleware(['auth', 'user.active', 'password.change', 'use.case:link-residents-to-units'])->group(function () {
    Route::get('unit-occupancies', [UnitOccupancyController::class, 'index']);
    Route::get('unit-occupancies/{id}', [UnitOccupancyController::class, 'show']);
    Route::post('unit-occupancies', [UnitOccupancyController::class, 'store']);
    Route::delete('unit-occupancies/{id}', [UnitOccupancyController::class, 'destroy']);
});

/*
| Área do morador.
|
| Além de autenticado e ativo, aqui o usuário precisa estar associado a uma
| unidade: reserva e ocorrência sem vínculo produziriam registro órfão, sem a
| que unidade cobrar a reserva nem de onde partiu a ocorrência. O middleware
| unit.linked deixa passar quem responde pelo condomínio inteiro — síndico,
| administrador e funcionário —, exigindo vínculo apenas do morador.
|
| As rotas de cada módulo entram aqui com a task correspondente.
*/
Route::middleware(['auth', 'user.active', 'password.change', 'unit.linked'])->group(function () {
    /*
    | UC04 / RF04 / RN05 — abertura de chamado de ocorrência.
    |
    | Uma rota só para os dois jeitos de abrir. O morador abre o próprio
    | chamado; o síndico abre no lugar de quem não conseguir, informando
    | `resident_id`. Separar em duas rotas duplicaria a mesma regra de criação
    | em dois lugares, e elas sairiam de acordo na primeira alteração.
    |
    | O middleware libera quem tiver **qualquer** um dos dois casos de uso;
    | quem pode usar o campo `resident_id` é a Action que decide, porque isso
    | depende do corpo da requisição, que o middleware não lê.
    */
    Route::post('occurrences', [OccurrenceController::class, 'store'])
        ->middleware('use.case:create-occurrence,open-occurrence-for-resident');
});

/*
|--------------------------------------------------------------------------
| Andamento e encerramento de chamados — RF04 / RN06 / RN15
|--------------------------------------------------------------------------
|
| Fora do `unit.linked`: quem age aqui é a administração, o síndico ou o
| funcionário, e nenhum deles responde por uma unidade.
|
| O grupo só abre a porta para quem tem algum dos casos de uso. O que cada um
| pode fazer em **cada chamado** é decidido nas Actions, porque depende do
| registro: o funcionário só mexe no que lhe foi atribuído, e só encerra o
| tipo que permite (`occurrence_types.employee_can_close`).
|
| Concluir não passa pelo PATCH de status: vai pelo POST de finalização, que
| exige a conclusão e se o problema foi resolvido.
*/
Route::middleware(['auth', 'user.active', 'password.change'])->group(function () {
    Route::patch('occurrences/{id}/status', [OccurrenceController::class, 'updateStatus'])
        ->middleware('use.case:analyze-occurrences,update-occurrence-progress');

    Route::post('occurrences/{id}/finish', [OccurrenceController::class, 'finish'])
        ->middleware('use.case:finish-occurrence,close-any-occurrence');

    // Ocorrencia.encaminhar(). Uma rota para os dois jeitos: administração e
    // síndico encaminham qualquer chamado, o funcionário repassa o seu. Quem
    // pode o quê em cada chamado é decidido na AssignOccurrenceAction.
    Route::patch('occurrences/{id}/assign', [OccurrenceController::class, 'assign'])
        ->middleware('use.case:assign-occurrence,hand-over-occurrence');

    // Para o seletor de quem recebe: sem ela, só o administrador — único com
    // a listagem de usuários — saberia para quem encaminhar.
    Route::get('assignable-employees', [OccurrenceController::class, 'assignableEmployees'])
        ->middleware('use.case:assign-occurrence,hand-over-occurrence');
});

/*
|--------------------------------------------------------------------------
| Imagens do chamado — RF04
|--------------------------------------------------------------------------
|
| O envio é multipart/form-data, no campo `image`.
|
| Entra quem acompanha chamados por algum caminho — o morador os próprios, o
| funcionário os atribuídos, o síndico e a administração todos. Qual chamado
| cada um alcança é decidido por registro, na CheckOccurrenceAccessAction, com
| a mesma regra da listagem.
|
| A imagem nunca é servida como arquivo estático: sai pela rota `show`, que
| confere o acesso antes de entregar. Um link direto para o disco seria
| autorização por quem conhece o endereço.
*/
Route::middleware([
    'auth',
    'user.active',
    'password.change',
    'use.case:track-own-occurrences,view-assigned-occurrences,analyze-occurrences,close-any-occurrence',
])->group(function () {
    Route::get('occurrences/{id}/attachments', [OccurrenceAttachmentController::class, 'index']);
    Route::post('occurrences/{id}/attachments', [OccurrenceAttachmentController::class, 'store']);
    Route::get('occurrences/{id}/attachments/{attachment}', [OccurrenceAttachmentController::class, 'show'])
        ->name('occurrences.attachments.show');
});

/*
|--------------------------------------------------------------------------
| Tipos de ocorrência — RF04
|--------------------------------------------------------------------------
|
| A lista deixou de ser texto livre e virou cadastro: síndico e administrador
| acrescentam, editam e aposentam tipos sem depender de deploy.
|
| A consulta e a manutenção estão em grupos diferentes de propósito. Ler a
| lista é insumo para abrir chamado — o morador precisa dela para escolher —,
| enquanto alterá-la é ato administrativo.
*/
Route::middleware(['auth', 'user.active', 'password.change'])->group(function () {
    Route::middleware('use.case:create-occurrence,open-occurrence-for-resident,manage-occurrence-types')->group(function () {
        Route::get('occurrence-types', [OccurrenceTypeController::class, 'index']);
        Route::get('occurrence-types/{id}', [OccurrenceTypeController::class, 'show']);
    });

    Route::middleware('use.case:manage-occurrence-types')->group(function () {
        Route::post('occurrence-types', [OccurrenceTypeController::class, 'store']);
        Route::patch('occurrence-types/{id}', [OccurrenceTypeController::class, 'update']);
        Route::delete('occurrence-types/{id}', [OccurrenceTypeController::class, 'destroy']);
    });
});
