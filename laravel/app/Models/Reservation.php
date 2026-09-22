<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'common_area_id',
        'resident_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'rejection_reason',
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
            'date' => 'date:Y-m-d',
            'status' => ReservationStatus::class,
        ];
    }

    public function commonArea(): BelongsTo
    {
        return $this->belongsTo(CommonArea::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    /**
     * RN01/RN08 — apenas reservas pendentes e aprovadas ocupam a agenda.
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', ReservationStatus::blocking());
    }

    /** Reserva de diária: ocupa o dia inteiro da área. */
    public function isFullDay(): bool
    {
        return $this->start_time === null && $this->end_time === null;
    }
}
