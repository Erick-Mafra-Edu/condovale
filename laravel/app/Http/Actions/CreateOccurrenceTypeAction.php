<?php

namespace App\Http\Actions;

use App\Enums\TypeLogEnum;
use App\Models\OccurrenceType;

/**
 * A administração acrescenta um tipo de chamado.
 *
 * Os assuntos recorrentes mudam de condomínio para condomínio e ao longo do
 * tempo; se a lista fosse enum, cada tipo novo exigiria deploy.
 */
class CreateOccurrenceTypeAction
{
    /**
     * @param  array{name: string, description?: string|null, active?: bool}  $attributes
     */
    public static function execute(array $attributes): OccurrenceType
    {
        $type = OccurrenceType::create([
            'name' => trim($attributes['name']),
            'description' => $attributes['description'] ?? null,
            'employee_can_close' => $attributes['employee_can_close'] ?? false,
            'active' => $attributes['active'] ?? true,
        ]);

        $description = "Tipo de ocorrência {$type->id} {$type->name} criado.";
        CreateLogAction::execute(TypeLogEnum::OCCURRENCE_TYPE->value, $description, $type);

        return $type;
    }
}
