<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Support\Str;

/**
 * RN03 — alteração estrutural: cadastra uma unidade do condomínio.
 */
class CreateUnitAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function execute(array $attributes): Unit
    {
        $unit = Unit::create([
            // O código é a identidade da unidade nos relatórios e no vínculo
            // com o morador; em maiúsculas para "a-101" e "A-101" não virarem
            // duas unidades diferentes.
            'code' => Str::upper(trim($attributes['code'])),
            'number' => trim($attributes['number']),
            'block' => isset($attributes['block']) ? trim((string) $attributes['block']) : null,
            'status' => $attributes['status'] ?? UnitStatus::Active->value,
        ]);

        $description = "Unidade {$unit->id} {$unit->code} cadastrada.";
        CreateLogAction::execute(TypeLogEnum::UNIT->value, $description, $unit);

        return $unit;
    }
}
