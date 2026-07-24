<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpsCpl extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rps_cpl';

    protected $fillable = [
        'id_rps',
        'cpl',
        'cpl_en',
    ];

    protected $casts = [
        'id_rps' => 'integer',
    ];

    public function rps(): BelongsTo
    {
        return $this->belongsTo(Rps::class, 'id_rps');
    }
}
