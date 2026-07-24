<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasAkhirStatusLog extends Model
{
    protected $table = 'tugas_akhir_status_logs';

    protected $fillable = [
        'id_tugas_akhir',
        'status',
        'keterangan',
        'id_user',
    ];

    protected $casts = [
        'id_tugas_akhir' => 'integer',
        'id_user' => 'integer',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'id_tugas_akhir');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
