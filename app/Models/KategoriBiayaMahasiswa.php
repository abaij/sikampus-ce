<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\KategoriBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
class KategoriBiayaMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kategori_biaya_mahasiswa';
    protected $fillable = ['id_kategori_biaya', 'id_mahasiswa', 'id_semester', 'status'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_kategori_biaya' => 'integer',
        'id_mahasiswa' => 'integer',
        'id_semester' => 'integer',
    ];

    public function kategori_biaya()
    {
        return $this->belongsTo(KategoriBiaya::class, 'id_kategori_biaya');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}
