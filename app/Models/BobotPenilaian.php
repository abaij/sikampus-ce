<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class BobotPenilaian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bobot_penilaian';
    protected $fillable = [
        'id_kurikulum_matkul',
        'id_jenis_penilaian',
        'bobot',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_kurikulum_matkul' => 'integer',
        'id_jenis_penilaian' => 'integer',
        'bobot' => 'decimal:2',
    ];

    public function kurikulumMatkul()
    {
        return $this->belongsTo(KurikulumMatkul::class, 'id_kurikulum_matkul');
    }

    public function jenisPenilaian()
    {
        return $this->belongsTo(JenisPenilaian::class, 'id_jenis_penilaian');
    }
}
