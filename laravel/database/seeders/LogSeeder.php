<?php

namespace Database\Seeders;

use App\Models\Log;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@condovale.com')->first();

        if ($admin) {
            Log::firstOrCreate(
                ['description' => 'Carga inicial e homologação da base de dados realizada com sucesso.'],
                [
                    'type_log_id' => 1,
                    'user_id' => $admin->id,
                    'ip' => '127.0.0.1',
                    'data_log' => ['system' => 'CondoVale', 'environment' => 'development'],
                ]
            );
        }
    }
}
