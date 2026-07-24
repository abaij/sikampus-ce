<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class KelasDosen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kelas_dosen';

    protected $fillable = [
        'id_kelas',
        'id_dosen',
        'is_pic',
    ];

    protected $casts = [
        'id_kelas' => 'integer',
        'id_dosen' => 'integer',
        'is_pic' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }
}
