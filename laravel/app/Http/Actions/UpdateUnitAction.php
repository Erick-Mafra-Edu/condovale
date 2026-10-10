<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Support\Str;

/**
 * RN03 — alteração estrutural: edita os dados da unidade.
 *
 * A reativação é por aqui, mandando `status: active`; não existe endpoint
 * próprio para ela.
 */
class UpdateUnitAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function execute(Unit $unit, array $attributes): Unit
    {
        if (array_key_exists('code', $attributes)) {
            $attributes['code'] = Str::upper(trim($attributes['code']));
        }

        if (self::estaInativando($unit, $attributes)) {
            DeactivateUnitAction::checkCanDeactivate($unit);
        }

        $unit->update($attributes);

        $description = "Unidade {$unit->id} {$unit->code} editada.";
        CreateLogAction::execute(TypeLogEnum::UNIT->value, $description, (object) $attributes);

        return $unit->refresh();
    }

    private static function estaInativando(Unit $unit, array $attributes): bool
    {
        if (! array_key_exists('status', $attributes)) {
            return false;
        }

        return $attributes['status'] === UnitStatus::Inactive->value && $unit->isActive();
    }
}
