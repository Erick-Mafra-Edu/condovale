<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Http\Utils\TimeUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    /**
     * How many times a transaction that writes reservations runs again when
     * InnoDB picks it as a deadlock victim ("try restarting transaction").
     * Creating, approving and cancelling lock the area and the reservations
     * of the day; under contention one of them may be chosen, and running it
     * again is the remedy MySQL itself prescribes.
     */
    public const TRANSACTION_ATTEMPTS = 3;

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

    /**
     * MySQL returns a TIME column as "HH:MM:SS"; the API speaks "HH:MM", the
     * same the reservation screen sends and compares its grid with.
     */
    protected function startTime(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => TimeUtil::format($value));
    }

    /** A reservation that ends at midnight reads "24:00", never "00:00". */
    protected function endTime(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => TimeUtil::formatEnd($value));
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
