<?php

namespace App\Models;

use App\Enums\OccurrenceHistoryType;
use App\Enums\OccurrenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccurrenceHistory extends Model
{
    use HasFactory;

    protected $table = 'occurrence_histories';

    protected $fillable = [
        'occurrence_id',
        'user_id',
        'type',
        'message',
        'previous_status',
        'new_status',
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
            'type' => OccurrenceHistoryType::class,
            'previous_status' => OccurrenceStatus::class,
            'new_status' => OccurrenceStatus::class,
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
