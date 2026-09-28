<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeployController;
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
Route::post('deploy/migrate', [DeployController::class, 'migrate'])
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

Route::middleware(['auth', 'user.active'])->group(function () {
    //
});
