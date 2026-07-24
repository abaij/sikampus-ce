<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpsPembelajaran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rps_pembelajaran';

    protected $fillable = [
        'id_rps',
        'urutan_pertemuan',
        'sub_cpmk',
        'indikator_penilaian',
        'bentuk_kriteria_penilaian',
        'pembelajaran_sinkron',
        'pembelajaran_asinkron',
        'materi',
        'materi_en',
        'bobot',
    ];

    protected $casts = [
        'id_rps' => 'integer',
        'urutan_pertemuan' => 'integer',
        'bobot' => 'decimal:2',
    ];

    public function rps(): BelongsTo
    {
        return $this->belongsTo(Rps::class, 'id_rps');
    }
}
