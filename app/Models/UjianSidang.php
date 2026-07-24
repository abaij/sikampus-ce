<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UjianSidang extends Model
{
    use SoftDeletes;

    protected $table = 'ujian_sidang';

    protected $fillable = [
        'id_tugas_akhir',
        'id_semester',
        'tanggal_daftar',
        'status',
        'file_proposal',
        'tanggal_ujian_mulai',
        'tanggal_ujian_selesai',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id_tugas_akhir' => 'integer',
        'id_semester' => 'integer',
        'tanggal_daftar' => 'datetime',
        'tanggal_ujian_mulai' => 'datetime',
        'tanggal_ujian_selesai' => 'datetime',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'id_tugas_akhir');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function penguji(): HasMany
    {
        return $this->hasMany(UjianSidangPenguji::class, 'id_ujian_sidang')
            ->orderByDesc('is_ketua')
            ->orderBy('id');
    }
}
