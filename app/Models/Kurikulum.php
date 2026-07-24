<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kurikulum extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kurikulum';
    protected $fillable = [
        'id_prodi',
        'kode',
        'nama',
        'sks_wajib_minimal',
        'id_tahun_berlaku',
        'status',
        'deskripsi',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_prodi' => 'integer',
        'id_tahun_berlaku' => 'integer',
        'sks_wajib_minimal' => 'integer',
    ];

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    /** Semester acuan tahun/masa berlaku kurikulum */
    public function tahunBerlaku()
    {
        return $this->belongsTo(Semester::class, 'id_tahun_berlaku');
    }

    public function matkuls()
    {
        return $this->belongsToMany(Matkul::class, 'kurikulum_matkul', 'id_kurikulum', 'id_matkul')
                    ->withPivot('id', 'kode_matkul', 'nama_matkul', 'nama_matkul_en', 'sks', 'semester_rekomendasi', 'is_wajib')
                    ->withTimestamps();
    }
}

