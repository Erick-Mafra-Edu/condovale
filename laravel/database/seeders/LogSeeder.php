<?php

namespace Database\Seeders;

use App\Models\Log;
use Database\Seeders\Concerns\ResolvesReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Uma entrada na trilha de auditoria marcando a própria carga.
 *
 * A tabela de verdade é escrita pela CreateLogAction a cada operação; o que
 * entra aqui é só o registro de que a base foi recriada, para a listagem de
 * auditoria não aparecer vazia logo depois do deploy.
 */
class LogSeeder extends Seeder
{
    use ResolvesReferences;

    public function run(): void
    {
        $json = File::get(database_path('seeders/json/logs.json'));
        $data = json_decode($json);

        foreach ($data as $item) {
            $array = [
                'type_log_id' => $item->type_log_id,
                'user_id' => $this->userId($item->user_email, 'logs.json'),
                'description' => $item->description,
                'ip' => $item->ip,
                'data_log' => $item->data_log,
            ];

            Log::create($array);
        }
    }
}
