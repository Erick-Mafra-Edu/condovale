<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditEntity;
use App\Enums\TypeLogEnum;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail required by RNF04. Written only through CreateLogAction, so the
 * author, the timestamp and the IP always come from the server.
 */
class Log extends Model
{
    protected $fillable = [
        'type_log_id',
        'action',
        'subject_id',
        'user_id',
        'user_role',
        'description',
        'ip',
        'data_log',
    ];

    /**
     * The IP is an operational detail and never leaves the application.
     *
     * @var list<string>
     */
    protected $hidden = ['ip'];

    protected function casts(): array
    {
        return [
            'type_log_id' => 'integer',
            'subject_id' => 'integer',
            'data_log' => 'array',
        ];
    }

    /**
     * The trail is append only: a record that can be rewritten through the
     * application proves nothing. A correction is a new entry.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Um registro de auditoria não pode ser alterado.');
        });

        static::deleting(function () {
            throw new \LogicException('Um registro de auditoria não pode ser excluído.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
     * Read with tryFrom rather than enum casts: entries outlive the code, and
     * a value retired from an enum must not make the old entry unreadable.
     */

    public function auditAction(): ?AuditAction
    {
        return AuditAction::tryFrom((string) $this->action);
    }

    public function entity(): ?AuditEntity
    {
        return TypeLogEnum::tryFrom((int) $this->type_log_id)?->entity();
    }

    public function authorRole(): ?UserRole
    {
        return UserRole::tryFrom((string) $this->user_role);
    }
}
