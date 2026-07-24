<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisMatkul extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_matkul';
    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}

