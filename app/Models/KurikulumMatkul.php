<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class KurikulumMatkul extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kurikulum_matkul';
    protected $fillable = [
        'id_kurikulum',
        'id_matkul',
        'kode_matkul',
        'nama_matkul',
        'nama_matkul_en',
        'sks',
        'semester_rekomendasi',
        'is_wajib',
        'created_by',
        'updated_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_kurikulum' => 'integer',
        'id_matkul' => 'integer',
        'kode_matkul' => 'string',
        'nama_matkul' => 'string',
        'nama_matkul_en' => 'string',
        'sks' => 'integer',
        'semester_rekomendasi' => 'integer',
        'is_wajib' => 'boolean',
    ];

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    public function matkul()
    {
        return $this->belongsTo(Matkul::class, 'id_matkul');
    }

    public function bobotPenilaian()
    {
        return $this->hasMany(BobotPenilaian::class, 'id_kurikulum_matkul');
    }
}

