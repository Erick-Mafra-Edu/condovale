<?php

namespace App\Models;

use App\Enums\CommonAreaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommonArea extends Model
{
    use HasFactory;

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
}
