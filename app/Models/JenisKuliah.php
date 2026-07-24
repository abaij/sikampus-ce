<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisKuliah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_kuliah';
    protected $fillable = [
        'nama',
        'deskripsi',
        'status',
        'is_praktikum',
        'is_tugas_akhir',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'is_praktikum' => 'boolean',
        'is_tugas_akhir' => 'boolean',
    ];
}
