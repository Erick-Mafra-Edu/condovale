<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Utils\AuthUtil;
use App\Models\User;

/**
 * RF01 / RN04 — a administração inativa um usuário.
 *
 * Inativar **nunca apaga**: o registro fica, com `status = inactive`. As
 * reservas, ocorrências e linhas de auditoria daquela pessoa continuam
 * apontando para ela, e o histórico do condomínio não ganha buracos.
 *
 * O efeito é imediato mesmo para quem já está dentro: o middleware
 * `user.active` encerra a sessão na requisição seguinte.
 */
class DeactivateUserAction
{
    public static function execute(User $user): User
    {
        self::checkCanDeactivate($user);

        $user->update(['status' => UserStatus::Inactive->value]);

        $description = "Usuário {$user->id} {$user->name} inativado.";
        CreateLogAction::execute(TypeLogEnum::USER->value, $description);

        return $user->refresh();
    }

    /**
     * As duas travas que impedem o sistema de ficar sem administração.
     *
     * Vale tanto para o endpoint de inativação quanto para o PATCH que manda
     * `status: inactive` — senão a porta fechada numa ficaria aberta na outra.
     */
    public static function checkCanDeactivate(User $user): void
    {
        self::checkNotAlreadyInactive($user);
        self::checkNotSelf($user);
        self::checkNotLastAdministrator($user);
    }

    private static function checkNotAlreadyInactive(User $user): void
    {
        if (! $user->isActive()) {
            throw BusinessRuleException::conflict('Este usuário já está inativo.');
        }
    }

    /**
     * Quem se inativa não volta: a sessão cai na requisição seguinte e a conta
     * deixa de autenticar, sem caminho de recuperação pela interface.
     */
    private static function checkNotSelf(User $user): void
    {
        if (AuthUtil::id() === $user->id) {
            throw BusinessRuleException::forbidden('Você não pode inativar o seu próprio usuário.');
        }
    }

    /**
     * Sem administrador ativo não sobra quem cadastre, edite ou reative
     * ninguém — o sistema fica administrável só por acesso direto ao banco.
     */
    private static function checkNotLastAdministrator(User $user): void
    {
        if ($user->role !== UserRole::Admin) {
            return;
        }

        $outros = User::query()
            ->where('role', UserRole::Admin)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $outros) {
            throw BusinessRuleException::conflict(
                'Este é o único administrador ativo: promova outro antes de inativá-lo.'
            );
        }
    }
}
