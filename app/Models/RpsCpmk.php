<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpsCpmk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rps_cpmk';

    protected $fillable = [
        'id_rps',
        'cpmk',
        'cpmk_en',
    ];

    protected $casts = [
        'id_rps' => 'integer',
    ];

    public function rps(): BelongsTo
    {
        return $this->belongsTo(Rps::class, 'id_rps');
    }

    public function rpsSubcpmk(): HasMany
    {
        return $this->hasMany(RpsSubcpmk::class, 'id_cpmk')->orderBy('id');
    }
}
