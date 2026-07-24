<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisKeluar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_keluar';
    protected $fillable = ['nama'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
