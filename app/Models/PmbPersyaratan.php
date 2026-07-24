<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbPersyaratan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_persyaratan';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_periode',
        'nama',
        'keterangan',
        'is_wajib',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_wajib' => 'boolean',
        ];
    }

    /**
     * Get the periode that owns the persyaratan.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PmbPeriode::class, 'id_periode');
    }
}

