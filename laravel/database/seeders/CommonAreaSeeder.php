<?php

namespace Database\Seeders;

use App\Enums\CommonAreaStatus;
use App\Models\CommonArea;
use Illuminate\Database\Seeder;

class CommonAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            [
                'name' => 'Salão de Festas Principal',
                'description' => 'Salão equipado com churrasqueira, mesas, freezer e ar-condicionado.',
                'capacity' => 80,
                'opening_time' => '10:00:00',
                'closing_time' => '23:00:00',
                'max_reservation_minutes' => 780,
                'requires_approval' => true,
                'status' => CommonAreaStatus::Available,
            ],
            [
                'name' => 'Churrasqueira Externa',
                'description' => 'Espaço gourmet com churrasqueira a carvão e bancada de apoio.',
                'capacity' => 25,
                'opening_time' => '11:00:00',
                'closing_time' => '22:00:00',
                'max_reservation_minutes' => 360,
                'requires_approval' => false,
                'status' => CommonAreaStatus::Available,
            ],
            [
                'name' => 'Quadra Poliesportiva',
                'description' => 'Quadra demarcada para futsal, basquete e vôlei.',
                'capacity' => 20,
                'opening_time' => '08:00:00',
                'closing_time' => '22:00:00',
                'max_reservation_minutes' => 120,
                'requires_approval' => false,
                'status' => CommonAreaStatus::Available,
            ],
            [
                'name' => 'Academia',
                'description' => 'Espaço fitness com esteiras, bicicletas e halteres.',
                'capacity' => 15,
                'opening_time' => '06:00:00',
                'closing_time' => '23:00:00',
                'max_reservation_minutes' => 90,
                'requires_approval' => false,
                'status' => CommonAreaStatus::Available,
            ],
        ];

        foreach ($areas as $area) {
            CommonArea::firstOrCreate(['name' => $area['name']], $area);
        }
    }
}
