<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisPenilaian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_penilaian';
    protected $fillable = [
        'kode',
        'bobot',
        'nama',
        'status', // manual = diisi dosen, otomatis = otomatis oleh sistem
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
