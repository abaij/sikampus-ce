<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DosenWaliBimbingan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dosen_wali_bimbingan';

    protected $fillable = [
        'id_dosen_wali',
        'id_semester',
        'catatan_dosen',
        'catatan_mhs',
        'file',
        'tanggal_bimbingan',
        'waktu_validasi_dosen',
        'waktu_validasi_mhs',
    ];

    protected $casts = [
        'id_semester' => 'integer',
        'tanggal_bimbingan' => 'date',
        'waktu_validasi_dosen' => 'datetime',
        'waktu_validasi_mhs' => 'datetime',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file) {
            return null;
        }

        return asset('storage/' . ltrim($this->file, '/'));
    }

    public function dosenWali()
    {
        return $this->belongsTo(DosenWali::class, 'id_dosen_wali');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}
