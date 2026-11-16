<?php

namespace App\Http\Controllers;

use App\Http\Actions\BuildDashboardAction;
use App\Http\Utils\AuthUtil;
use App\Services\MessageService;

/**
 * O painel inicial de quem está logado.
 *
 * Todo perfil tem painel, então a rota não pede caso de uso: o que entra em
 * cada painel é decidido pela BuildDashboardAction, seção por seção, a partir
 * das permissões da pessoa.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = AuthUtil::user();

        try {
            $painel = BuildDashboardAction::execute($user);
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }

        return MessageService::success("Painel de {$user->name}.", $painel);
    }
}
