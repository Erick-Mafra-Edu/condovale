<?php

namespace Database\Seeders;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class OccurrenceSeeder extends Seeder
{
    public function run(): void
    {
        $morador1 = User::where('email', 'morador1@condovale.com')->first();
        $morador2 = User::where('email', 'morador2@condovale.com')->first();
        $manutencao = User::where('email', 'manutencao@condovale.com')->first();

        if ($morador1) {
            $occ1 = Occurrence::firstOrCreate(
                ['title' => 'Vazamento de água no corredor do 1º andar'],
                [
                    'description' => 'Há uma infiltração visível saindo do teto perto do elevador social.',
                    'category' => 'manutenção',
                    'resident_id' => $morador1->id,
                    'unit_id' => $morador1->unit_id,
                    'assigned_employee_id' => $manutencao?->id,
                    'status' => OccurrenceStatus::Assigned,
                ]
            );

            OccurrenceHistory::firstOrCreate([
                'occurrence_id' => $occ1->id,
                'user_id' => $morador1->id,
                'type' => OccurrenceHistoryType::Created,
                'new_status' => OccurrenceStatus::Open,
            ]);

            if ($manutencao) {
                OccurrenceHistory::firstOrCreate([
                    'occurrence_id' => $occ1->id,
                    'user_id' => $manutencao->id,
                    'type' => OccurrenceHistoryType::StatusChanged,
                    'previous_status' => OccurrenceStatus::Open,
                    'new_status' => OccurrenceStatus::Assigned,
                    'message' => 'Chamado encaminhado para a equipe de manutenção predial.',
                ]);
            }
        }

        if ($morador2) {
            $occ2 = Occurrence::firstOrCreate(
                ['title' => 'Lâmpada queimada na garagem B2'],
                [
                    'description' => 'A vaga 102 está escura devido à lâmpada queimada no poste 4.',
                    'category' => 'iluminação',
                    'resident_id' => $morador2->id,
                    'unit_id' => $morador2->unit_id,
                    'status' => OccurrenceStatus::Open,
                ]
            );

            OccurrenceHistory::firstOrCreate([
                'occurrence_id' => $occ2->id,
                'user_id' => $morador2->id,
                'type' => OccurrenceHistoryType::Created,
                'new_status' => OccurrenceStatus::Open,
            ]);
        }
    }
}
