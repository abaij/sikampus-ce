<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrukturBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'struktur_biaya';
    protected $fillable = [
        'id_kategori_biaya',
        'id_prodi',
        'id_angkatan',
        'id_periode',
        'id_komponen_biaya',
        'tahap',
        'nominal',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_kategori_biaya' => 'integer',
        'id_prodi' => 'integer',
        'id_angkatan' => 'integer',
        'id_periode' => 'integer',
        'id_komponen_biaya' => 'integer',
        'tahap' => 'integer',
        'nominal' => 'decimal:2',
    ];

    public function kategoriBiaya()
    {
        return $this->belongsTo(KategoriBiaya::class, 'id_kategori_biaya');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    /**
     * Angkatan (semester masuk mahasiswa) — FK ke tabel semester.
     */
    public function angkatan()
    {
        return $this->belongsTo(Semester::class, 'id_angkatan');
    }

    public function periode()
    {
        return $this->belongsTo(Semester::class, 'id_periode');
    }

    public function komponenBiaya()
    {
        return $this->belongsTo(KomponenBiaya::class, 'id_komponen_biaya');
    }
}
