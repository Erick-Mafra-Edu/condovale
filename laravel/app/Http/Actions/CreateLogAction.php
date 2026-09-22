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
            $attributes['data_log'] = json_encode($dataLog);
        }

        Log::create($attributes);
    }
}
