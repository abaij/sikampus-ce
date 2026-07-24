<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KelompokKelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kelompok_kelas';

    protected $fillable = [
        'nama',
        'id_prodi',
        'keterangan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_prodi' => 'integer',
    ];

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_kelompok_kelas');
    }

    public function mahasiswa()
    {
        return $this->hasMany(Mahasiswa::class, 'id_kelompok_kelas');
    }
}
