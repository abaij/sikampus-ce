<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisDaftar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_daftar';
    protected $fillable = ['nama', 'deskripsi'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}

