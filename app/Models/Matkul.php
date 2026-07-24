<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matkul extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'matkul';
    protected $fillable = [
        'kode',
        'nama',
        'nama_en',
        'deskripsi',
        'sks',
        'semester',
        'id_prodi',
        'id_jenis_matkul',
        'status',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'sks' => 'integer',
        'semester' => 'integer',
        'id_prodi' => 'integer',
        'id_jenis_matkul' => 'integer',
    ];

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function jenisMatkul()
    {
        return $this->belongsTo(JenisMatkul::class, 'id_jenis_matkul');
    }

    /**
     * Baris prasyarat (pivot) untuk mata kuliah ini.
     */
    public function matkulPrasyaratLinks()
    {
        return $this->hasMany(MatkulPrasyarat::class, 'id_matkul');
    }
}

