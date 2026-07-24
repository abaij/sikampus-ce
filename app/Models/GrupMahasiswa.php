<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrupMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'grup_mahasiswa';
    protected $fillable = ['nama', 'kode', 'angkatan', 'status'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'angkatan' => 'integer',
    ];
}
