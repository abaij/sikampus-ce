<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nilai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'nilai';
    protected $fillable = [
        'id_krs',
        'id_konversi_nilai',
        'sks',
        'angka_mutu',
        'huruf_mutu',
        'is_final',
        'revisi',
        'created_by',
        'updated_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_krs' => 'integer',
        'id_konversi_nilai' => 'integer',
        'sks' => 'integer',
        'angka_mutu' => 'decimal:2',
        'is_final' => 'boolean',
        'revisi' => 'integer',
    ];

    public function krs()
    {
        return $this->belongsTo(Krs::class, 'id_krs');
    }

    public function konversiNilai()
    {
        return $this->belongsTo(KonversiNilai::class, 'id_konversi_nilai');
    }
}
