<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Yudisium extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'yudisium';
    protected $fillable = [
        'id_mahasiswa',
        'id_jenis_keluar',
        'tgl_keluar',
        'no_ijazah',
        'no_sk_yudisium',
        'tanggal_sk_yudisium',
        'ipk',
        'judul_skripsi',
        'keterangan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_mahasiswa' => 'integer',
        'id_jenis_keluar' => 'integer',
        'ipk' => 'decimal:2',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function jenis_keluar()
    {
        return $this->belongsTo(JenisKeluar::class, 'id_jenis_keluar');
    }
}

