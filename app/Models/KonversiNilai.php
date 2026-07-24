<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KonversiNilai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'konversi_nilai';

    protected $fillable = [
        'id_mahasiswa',
        'id_kurikulum',
        'id_jenis_konversi',
        'id_nilai',
        'kode_mk_lama',
        'nama_mk_lama',
        'sks_lama',
        'nilai_lama',
        'kode_mk_baru',
        'nama_mk_baru',
        'sks_baru',
        'nilai_baru',
        'keterangan',
        'is_approved',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id_mahasiswa' => 'integer',
        'id_kurikulum' => 'integer',
        'id_jenis_konversi' => 'integer',
        'id_nilai' => 'integer',
        'sks_lama' => 'integer',
        'sks_baru' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    public function jenisKonversi()
    {
        return $this->belongsTo(JenisKonversiNilai::class, 'id_jenis_konversi');
    }

    public function nilai()
    {
        return $this->belongsTo(Nilai::class, 'id_nilai');
    }
}
