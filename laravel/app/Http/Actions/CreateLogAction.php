<?php

namespace App\Http\Actions;

use App\Http\Utils\AuthUtil;
use App\Models\Log;
use Illuminate\Support\Facades\Request;

class CreateLogAction
{
    /**
     * Stores the log entry in the database.
     */
    public static function execute(int $typeLogId, string $description, array|object|null $dataLog = null): void
    {
        $userId = AuthUtil::id() ?? 1;

        $attributes = [
            'type_log_id' => $typeLogId,
            'user_id' => $userId,
            'description' => $description,
            'ip' => Request::ip(),
        ];

        if ($dataLog) {
            // Sem json_encode aqui: o model declara o cast `array` em
            // data_log, que já serializa na gravação. Codificar antes fazia o
            // cast codificar a string de novo, e a coluna guardava JSON dentro
            // de JSON — ao ler de volta vinha uma string, não os dados. Quem
            // for montar o relatório de auditoria (RN16) receberia aspas
            // escapadas em vez do que mudou.
            $attributes['data_log'] = $dataLog;
        }

        Log::create($attributes);
    }
}
