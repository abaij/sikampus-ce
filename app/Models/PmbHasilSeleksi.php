<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbHasilSeleksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pmb_hasil_seleksi';

    protected $fillable = [
        'id_pendaftaran',
        'nilai',
        'peringkat',
        'keterangan',
        'status',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftaran::class, 'id_pendaftaran');
    }
}
