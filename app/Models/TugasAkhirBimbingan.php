<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TugasAkhirBimbingan extends Model
{
    use SoftDeletes;

    protected $table = 'tugas_akhir_bimbingan';

    protected $fillable = [
        'id_tugas_akhir',
        'id_dosen',
        'catatan_dosen',
        'catatan_mahasiswa',
        'file',
        'tanggal_bimbingan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id_tugas_akhir' => 'integer',
        'id_dosen' => 'integer',
        'tanggal_bimbingan' => 'date',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'id_tugas_akhir');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }
}
