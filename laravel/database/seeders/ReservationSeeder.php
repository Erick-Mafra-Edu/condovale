<?php

namespace Database\Seeders;

use App\Models\Reservation;
use Database\Seeders\Concerns\ResolvesReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Reservas nos quatro estados que o fluxo produz.
 *
 * A data é gravada como deslocamento em dias a partir de hoje, e não fixa no
 * JSON: uma reserva aprovada só se demonstra enquanto estiver no futuro, e
 * data fixa envelhece — a carga aplicada em outubro mostraria em dezembro um
 * calendário inteiro no passado, sem nada para aprovar nem cancelar.
 */
class ReservationSeeder extends Seeder
{
    use ResolvesReferences;

    public function run(): void
    {
        $json = File::get(database_path('seeders/json/reservations.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'common_area_id' => $this->commonAreaId($item->common_area_name, 'reservations.json'),
                'resident_id' => $this->userId($item->resident_email, 'reservations.json'),
                'date' => now()->addDays($item->days_from_today)->toDateString(),
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
                'status' => $item->status,
                'rejection_reason' => $item->rejection_reason,
            ];

            Reservation::create($array);
        }
    }
}
