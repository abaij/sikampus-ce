<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbNilaiTes extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pmb_nilai_tes';

    protected $fillable = [
        'id_pendaftaran',
        'id_jenis_tes',
        'nilai',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftaran::class, 'id_pendaftaran');
    }

    public function jenisTes(): BelongsTo
    {
        return $this->belongsTo(PmbJenisTes::class, 'id_jenis_tes');
    }
}
