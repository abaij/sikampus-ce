<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TugasAkhirPembimbing extends Model
{
    use SoftDeletes;

    protected $table = 'tugas_akhir_pembimbing';

    protected $fillable = [
        'id_tugas_akhir',
        'id_dosen',
        'peran',
        'tanggal_penugasan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id_tugas_akhir' => 'integer',
        'id_dosen' => 'integer',
        'tanggal_penugasan' => 'date',
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
