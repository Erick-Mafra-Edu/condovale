<?php

namespace App\Models;

use App\Enums\OccurrenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chamado de ocorrência (RF04).
 *
 * O status só anda pelo fluxo de OccurrenceStatus, e quem garante isso é o
 * gancho `updating` abaixo: toda gravação que muda o status passa pelo grafo,
 * venha de uma Action, de um comando ou de código que alguém escrever depois.
 * Conferir só nas Actions deixaria a regra valendo apenas para quem lembrasse
 * de chamá-las.
 *
 * Dois limites que são do Eloquent, não desta classe: `updateQuietly()` e o
 * update em massa pelo query builder (`Occurrence::where(...)->update(...)`)
 * não disparam eventos e passam por fora. Não use nenhum dos dois para mudar
 * status.
 *
 * A criação não é conferida, de propósito: o chamado nasce Aberto pela
 * CreateOccurrenceAction, que é a única porta de criação da aplicação, e as
 * factories precisam criar chamados em qualquer etapa para que os testes
 * consigam exercitar as transições a partir dela.
 */
class Occurrence extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Occurrence $occurrence) {
            if (! $occurrence->isDirty('status')) {
                return;
            }

            $anterior = $occurrence->getOriginal('status');

            if ($anterior instanceof OccurrenceStatus) {
                $anterior->assertCanTransitionTo($occurrence->status);
            }
        });
    }

    protected $fillable = [
        'title',
        'description',
        'occurrence_type_id',
        'resident_id',
        'unit_id',
        'assigned_employee_id',
        'status',
        'resolved',
        'conclusion',
        'completed_at',
    ];

    /**
     * Nenhuma coluna sensível: a listagem pode devolver a tabela inteira. A
     * declaração é explícita porque o HandlePaginationAction só protege o que
     * o model informar aqui.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'status' => OccurrenceStatus::class,
            'resolved' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function occurrenceType(): BelongsTo
    {
        return $this->belongsTo(OccurrenceType::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OccurrenceHistory::class);
    }
}
