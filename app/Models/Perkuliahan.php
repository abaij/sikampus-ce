<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Perkuliahan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'perkuliahan';

    protected $fillable = [
        'id_jadwal',
        'id_dosen',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'materi',
        'realisasi_materi',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_jadwal' => 'integer',
        'id_dosen' => 'integer',
        'tanggal' => 'date',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    /** Materi file terikat ke slot jadwal (bukan ke baris perkuliahan). */
    public function materiPerkuliahan()
    {
        return $this->hasMany(MateriPerkuliahan::class, 'id_jadwal', 'id_jadwal');
    }

    public function kehadiran()
    {
        return $this->hasMany(Kehadiran::class, 'id_perkuliahan');
    }
}
