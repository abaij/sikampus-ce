<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ujian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ujian';

    public const JENIS = ['UTS', 'UAS', 'PRAKTIKUM'];

    protected $fillable = [
        'id_kelas',
        'jenis_ujian',
        'id_ruangan',
        'id_semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id_kelas' => 'integer',
        'id_ruangan' => 'integer',
        'id_semester' => 'integer',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}
