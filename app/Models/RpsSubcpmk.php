<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpsSubcpmk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rps_subcpmk';

    protected $fillable = [
        'id_cpmk',
        'subcpmk',
        'subcpmk_en',
    ];

    protected $casts = [
        'id_cpmk' => 'integer',
    ];

    public function rpsCpmk(): BelongsTo
    {
        return $this->belongsTo(RpsCpmk::class, 'id_cpmk');
    }
}
