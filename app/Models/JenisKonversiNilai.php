<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisKonversiNilai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_konversi_nilai';
    protected $fillable = ['nama', 'keterangan', 'is_aktif','created_by','updated_by','deleted_by'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'is_aktif' => 'boolean',
    ];
}
