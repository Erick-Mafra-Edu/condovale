<?php

namespace App\Http\Actions;

use App\Enums\OccupantType;
use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;

/**
 * Vincula um morador a uma unidade.
 *
 * Toda a validação do diagrama de sequência ("Validar vínculo") acontece antes
 * da primeira escrita, de propósito: assim nenhum `return` antecipado precisa
 * desfazer transação pela metade.
 */
class LinkResidentToUnitAction
{
    public static function execute(array $attributes): UnitOccupancy
    {
        $user = self::findUser((int) $attributes['user_id']);
        $unit = self::findUnit((int) $attributes['unit_id']);

        self::checkResident($user);
        self::checkUnitIsActive($unit);
        self::checkNotAlreadyLinked($user, $unit);

        $occupancy = UnitOccupancy::create([
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'occupant_type' => $attributes['occupant_type'] ?? OccupantType::Tenant->value,
            'started_at' => $attributes['started_at'] ?? now()->toDateString(),
            'ended_at' => null,
            'is_active' => true,
        ]);

        // users.unit_id é atalho denormalizado da ocupação vigente. Mantê-lo em
        // acordo aqui, na mesma transação, é o que impede as duas fontes de
        // divergirem — nenhum outro ponto do sistema escreve nessa coluna.
        $user->update(['unit_id' => $unit->id]);

        $description = "Morador {$user->id} {$user->name} vinculado à unidade {$unit->id} {$unit->code}.";
        CreateLogAction::execute(TypeLogEnum::UNIT_OCCUPANCY->value, $description, $occupancy);

        return $occupancy;
    }

    /**
     * Encerra o vínculo sem apagar a linha: o histórico é o que permite saber
     * quem ocupava a unidade na data de uma ocorrência antiga.
     */
    public static function finish(UnitOccupancy $occupancy, ?string $endedAt = null): UnitOccupancy
    {
        if (! $occupancy->is_active) {
            throw BusinessRuleException::conflict('Este vínculo já está encerrado.');
        }

        $endedAt = $endedAt ?? now()->toDateString();

        if ($endedAt < $occupancy->started_at->toDateString()) {
            throw BusinessRuleException::unprocessable('O encerramento não pode ser anterior ao início do vínculo.');
        }

        $occupancy->update([
            'ended_at' => $endedAt,
            'is_active' => false,
        ]);

        self::refreshCurrentUnit($occupancy->user);

        $unit = $occupancy->unit;
        $user = $occupancy->user;
        $description = "Vínculo do morador {$user->id} {$user->name} com a unidade {$unit->id} {$unit->code} encerrado.";
        CreateLogAction::execute(TypeLogEnum::UNIT_OCCUPANCY->value, $description, $occupancy);

        return $occupancy;
    }

    /**
     * Reaponta users.unit_id para a ocupação vigente que sobrou, ou limpa a
     * coluna quando não sobrou nenhuma.
     */
    private static function refreshCurrentUnit(User $user): void
    {
        $current = $user->activeUnitOccupancies()->latest('started_at')->first();

        $user->update(['unit_id' => $current?->unit_id]);
    }

    private static function findUser(int $id): User
    {
        $user = User::find($id);

        if (! $user) {
            throw BusinessRuleException::notFound("Usuário {$id} não encontrado.");
        }

        return $user;
    }

    private static function findUnit(int $id): Unit
    {
        $unit = Unit::find($id);

        if (! $unit) {
            throw BusinessRuleException::notFound("Unidade {$id} não encontrada.");
        }

        return $unit;
    }

    /**
     * O vínculo é entre morador e unidade. Funcionário, síndico e
     * administrador respondem pelo condomínio inteiro: vinculá-los a uma
     * unidade daria a eles, na prática, um endereço que eles não têm.
     */
    private static function checkResident(User $user): void
    {
        if ($user->role !== UserRole::Resident) {
            throw BusinessRuleException::unprocessable(
                'Somente usuários com perfil de morador podem ser vinculados a uma unidade.'
            );
        }

        if (! $user->isActive()) {
            throw BusinessRuleException::unprocessable('Não é possível vincular um usuário inativo.');
        }
    }

    private static function checkUnitIsActive(Unit $unit): void
    {
        if (! $unit->isActive()) {
            throw BusinessRuleException::unprocessable("A unidade {$unit->code} está inativa.");
        }
    }

    /**
     * O mesmo morador pode ocupar a mesma unidade de novo no futuro — o que não
     * pode é ter dois vínculos vigentes com ela ao mesmo tempo, que duplicaria
     * o morador na lista da unidade.
     */
    private static function checkNotAlreadyLinked(User $user, Unit $unit): void
    {
        $exists = UnitOccupancy::query()
            ->where('user_id', $user->id)
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw BusinessRuleException::conflict("O morador já está vinculado à unidade {$unit->code}.");
        }
    }
}
