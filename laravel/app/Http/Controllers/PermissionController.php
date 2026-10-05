<?php

namespace App\Http\Controllers;

use App\Http\Actions\HandlePaginationAction;
use App\Http\Requests\DefaultPaginationRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\MessageService;

/**
 * Catálogo do que existe para conceder.
 *
 * É o que a interface precisa para montar a tela de permissões sem ter a
 * matriz escrita de novo do lado dela.
 */
class PermissionController extends Controller
{
    public function index(DefaultPaginationRequest $request, Permission $permission)
    {
        $item = (object) $request->validated();
        $searchColumns = ['name'];

        $response = HandlePaginationAction::execute($request, $permission, $searchColumns);
        $permissions = $response->paginate($item->limit ?? 50);

        return MessageService::success('Casos de uso retornados.', $permissions, true);
    }

    /**
     * Papéis com os casos de uso de cada um.
     *
     * Sem paginação de propósito: são quatro papéis, e a tela precisa deles
     * inteiros para mostrar o que cada perfil já concede.
     */
    public function roles()
    {
        $roles = Role::with('permissions:id,name')->get()->map(fn (Role $role) => [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values(),
        ]);

        return MessageService::success('Perfis retornados.', $roles);
    }
}
