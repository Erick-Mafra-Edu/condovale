<?php

namespace App\Models;

use App\Enums\CommonAreaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommonArea extends Model
{
    use HasFactory;

    /** RN14 — reservations by time are made of 30 minute blocks from opening time. */
    public const SLOT_MINUTES = 30;

    /** RN14 — the limit applied when the area does not inform its own. */
    public const DEFAULT_MAX_RESERVATION_MINUTES = 120;

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'capacity',
        'opening_time',
        'closing_time',
        'max_reservation_minutes',
        'requires_approval',
        'status',
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
            'capacity' => 'integer',
            'max_reservation_minutes' => 'integer',
            'requires_approval' => 'boolean',
            'status' => CommonAreaStatus::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === CommonAreaStatus::Available;
    }

    /**
     * The maximum length of a reservation by time. The reservation screen
     * already falls back to two hours when the area has no limit; the API
     * applies the same number, so the rule does not live only in the browser.
     */
    public function maxReservationMinutes(): int
    {
        return $this->max_reservation_minutes ?? self::DEFAULT_MAX_RESERVATION_MINUTES;
    }

    /** Without opening and closing time there is no grid to book from. */
    public function hasOpeningHours(): bool
    {
        return $this->opening_time !== null && $this->closing_time !== null;
    }
}
