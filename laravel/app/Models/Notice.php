<?php

namespace App\Models;

use App\Enums\NoticeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'status',
        'published_at',
    ];

    /**
     * Nenhuma coluna sensível. A declaração é explícita porque o
     * HandlePaginationAction só protege o que o model informar aqui.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'status' => NoticeStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
