<?php

namespace App\Http\Actions;

use App\Enums\AuditAction;
use App\Http\Utils\AuthUtil;
use App\Models\Log;
use Illuminate\Support\Facades\Request;

class CreateLogAction
{
    /**
     * Stores the log entry in the database.
     *
     * RNF04 — besides the sentence, the entry records what was done
     * (`$action`) and to which record (`$subjectId`, whose kind comes from the
     * type), so the trail can be filtered by operation and the history of one
     * record rebuilt. Author, role, time and IP always come from the server.
     */
    public static function execute(
        int $typeLogId,
        string $description,
        array|object|null $dataLog = null,
        ?AuditAction $action = null,
        ?int $subjectId = null,
    ): void {
        if ($action && $action->typeLog()->value !== $typeLogId) {
            throw new \LogicException("A ação {$action->value} não pertence ao tipo de log {$typeLogId}.");
        }

        $user = AuthUtil::user();

        $attributes = [
            'type_log_id' => $typeLogId,
            'action' => $action?->value,
            'subject_id' => $subjectId,
            'user_id' => $user?->id ?? 1,
            // Copied, not joined: the report must show the role the person had
            // when acting, not the one they have today.
            'user_role' => $user?->role?->value,
            'description' => $description,
            'ip' => Request::ip(),
        ];

        if ($dataLog) {
            // Sem json_encode aqui: o model declara o cast `array` em
            // data_log, que já serializa na gravação. Codificar antes fazia o
            // cast codificar a string de novo, e a coluna guardava JSON dentro
            // de JSON — ao ler de volta vinha uma string, não os dados.
            $attributes['data_log'] = $dataLog;
        }

        Log::create($attributes);
    }
}
