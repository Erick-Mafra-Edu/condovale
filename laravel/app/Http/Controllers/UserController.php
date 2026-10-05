<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Actions\CreateUserAction;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Utils\SanitizeUtil;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()->orderBy('name')->get();

        return MessageService::success('Usuários retornados.', $users);
    }

    public function show(int $id)
    {
        $user = User::find(SanitizeUtil::sanitizeInt($id));

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        return MessageService::success('Usuário encontrado.', $user);
    }

    public function store(UserStoreRequest $request)
    {
        try {
            return DB::transaction(fn () => MessageService::success(
                'Usuário criado.',
                CreateUserAction::execute($request->validated())
            ));
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }
    }

    public function update(UserUpdateRequest $request, int $id)
    {
        $user = User::find(SanitizeUtil::sanitizeInt($id));

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        try {
            $user->update($request->validated());

            return MessageService::success('Usuário atualizado.', $user->refresh());
        } catch (\Throwable $th) {
            return MessageService::throwable($th);
        }
    }

    public function deactivate(int $id)
    {
        $user = User::find(SanitizeUtil::sanitizeInt($id));

        if (! $user) {
            return MessageService::error("Usuário {$id} não encontrado.", 404);
        }

        $user->update(['status' => UserStatus::Inactive]);

        return MessageService::success('Usuário inativado.', $user->refresh());
    }
}
