<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UnitOccupancyController;
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
Route::middleware(['auth', 'user.active', 'use.case:manage-residents'])->group(function () {
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('roles', [PermissionController::class, 'roles']);

    Route::get('users/{id}/permissions', [UserPermissionController::class, 'index']);
    Route::post('users/{id}/permissions', [UserPermissionController::class, 'store']);
    Route::delete('users/{id}/permissions/{permission}', [UserPermissionController::class, 'destroy']);
    Route::patch('users/{id}/role', [UserPermissionController::class, 'updateRole']);
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
Route::middleware(['auth', 'user.active', 'use.case:link-residents-to-units'])->group(function () {
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
Route::middleware(['auth', 'user.active', 'unit.linked'])->group(function () {
    //
});
