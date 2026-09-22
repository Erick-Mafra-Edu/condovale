<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail required by RNF04. Written only through CreateLogAction, so the
 * author, the timestamp and the IP always come from the server.
 */
class Log extends Model
{
    protected $fillable = ['type_log_id', 'user_id', 'description', 'ip', 'data_log'];

    /**
     * The IP is an operational detail and never leaves the application.
     *
     * @var list<string>
     */
    protected $hidden = ['ip'];

    protected function casts(): array
    {
        return ['data_log' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
