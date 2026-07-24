<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TugasMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tugas_mahasiswa';
    protected $fillable = [
        'id_tugas',
        'id_mahasiswa',
        'file',
        'keterangan',
        'tanggal_submit',
        'status',
    ];

    protected $casts = [
        'tanggal_submit' => 'datetime',
        'status' => 'string',
    ];

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class, 'id_tugas');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }
}

