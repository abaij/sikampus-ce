<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TugasAkhir extends Model
{
    use SoftDeletes;

    protected $table = 'tugas_akhir';

    protected $fillable = [
        'id_mahasiswa',
        'id_semester',
        'judul',
        'judul_en',
        'topik',
        'topik_en',
        'deskripsi',
        'file',
        'created_by',
        'updated_by',
        'deleted_by',
        'status',
        'is_proposal',
    ];

    protected $casts = [
        'is_proposal' => 'boolean',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function bimbingan(): HasMany
    {
        return $this->hasMany(TugasAkhirBimbingan::class, 'id_tugas_akhir');
    }

    public function pembimbingPenguji(): HasMany
    {
        return $this->hasMany(TugasAkhirPembimbing::class, 'id_tugas_akhir')
            ->orderBy('peran')
            ->orderBy('id');
    }

    /** Hanya peran pembimbing (bukan penguji sidang — penguji ada di ujian_sidang_penguji). */
    public function pembimbing(): HasMany
    {
        return $this->hasMany(TugasAkhirPembimbing::class, 'id_tugas_akhir')
            ->where('peran', 'pembimbing')
            ->orderBy('id');
    }

    public function ujianSidang(): HasMany
    {
        return $this->hasMany(UjianSidang::class, 'id_tugas_akhir')
            ->orderByDesc('tanggal_ujian_mulai')
            ->orderByDesc('id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TugasAkhirStatusLog::class, 'id_tugas_akhir')
            ->orderByDesc('id');
    }
}
