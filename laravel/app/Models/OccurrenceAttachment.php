<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagem anexada a um chamado (RF04).
 */
class OccurrenceAttachment extends Model
{
    protected $fillable = [
        'occurrence_id',
        'uploaded_by',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    /**
     * O caminho no disco nunca sai da aplicação: quem quer a imagem pede pela
     * rota, que confere se a pessoa enxerga o chamado. Expor o caminho seria
     * convite a buscar o arquivo por outro lado.
     *
     * @var list<string>
     */
    protected $hidden = ['path'];

    /** @var list<string> */
    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            // Comparado com o id de quem pede a exclusão: sem o cast, um
            // driver que devolva a coluna como texto faria "5" !== 5.
            'uploaded_by' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Endereço por onde a imagem é entregue — autenticado, e não um link
     * direto para o arquivo.
     */
    public function getUrlAttribute(): string
    {
        return route('occurrences.attachments.show', [
            'id' => $this->occurrence_id,
            'attachment' => $this->id,
        ]);
    }
}
