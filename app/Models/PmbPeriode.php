<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbPeriode extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_periode';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'kode',
        'pilih_prodi_max',
        'keterangan',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
            'pilih_prodi_max' => 'integer',
        ];
    }

    /**
     * Get the persyaratan for the periode.
     */
    public function persyaratan(): HasMany
    {
        return $this->hasMany(PmbPersyaratan::class, 'id_periode');
    }

    /**
     * Get the biaya for the periode.
     */
    public function biaya(): HasMany
    {
        return $this->hasMany(PmbBiaya::class, 'id_periode');
    }
}

