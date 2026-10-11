<?php

namespace Database\Seeders;

use App\Models\UnitOccupancy;
use App\Models\User;
use Database\Seeders\Concerns\ResolvesReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * O histórico de quem ocupou cada unidade, incluindo vínculos já encerrados.
 *
 * Encerrar não apaga a linha: marca `is_active = false` e preenche `ended_at`.
 * A carga traz os dois casos porque é a diferença entre eles que a listagem de
 * moradores precisa mostrar.
 */
class UnitOccupancySeeder extends Seeder
{
    use ResolvesReferences;

    public function run(): void
    {
        $json = File::get(database_path('seeders/json/unit_occupancies.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $unitId = $this->unitId($item->unit_code, 'unit_occupancies.json');
            $userId = $this->userId($item->user_email, 'unit_occupancies.json');

            $array = [
                'unit_id' => $unitId,
                'user_id' => $userId,
                'occupant_type' => $item->occupant_type,
                'started_at' => $item->started_at,
                'ended_at' => $item->ended_at,
                'is_active' => $item->is_active,
            ];

            UnitOccupancy::create($array);

            // O atalho users.unit_id acompanha apenas a ocupação vigente, como
            // faz a LinkResidentToUnitAction. Vínculo encerrado não mexe nele:
            // do contrário a última linha do arquivo — que pode ser a antiga —
            // definiria onde a pessoa mora hoje.
            if ($item->is_active) {
                User::where('id', $userId)->update(['unit_id' => $unitId]);
            }
        }
    }
}
