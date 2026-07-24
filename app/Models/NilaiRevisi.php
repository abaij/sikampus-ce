<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class NilaiRevisi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'nilai_revisi';
    protected $fillable = [
        'id_krs',
        'angka_mutu',
        'huruf_mutu',
        'keterangan',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'id_krs' => 'integer',
        'angka_mutu' => 'decimal:2',
    ];

    public function krs()
    {
        return $this->belongsTo(Krs::class, 'id_krs');
    }
}
