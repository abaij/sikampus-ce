<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbEmailLog extends Model
{
    use SoftDeletes;

    protected $table = 'pmb_email_logs';

    protected $fillable = [
        'id_camaba',
        'email',
        'subject',
        'body',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function camaba(): BelongsTo
    {
        return $this->belongsTo(PmbCamaba::class, 'id_camaba');
    }
}
