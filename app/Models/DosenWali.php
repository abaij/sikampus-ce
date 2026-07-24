<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DosenWali extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dosen_wali';
    protected $fillable = ['id_dosen', 'id_mahasiswa', 'status'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_dosen' => 'integer',
        'id_mahasiswa' => 'integer',
        'status' => 'string',
    ];

    const STATUS = [
        'active',
        'inactive',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function bimbingan()
    {
        return $this->hasMany(DosenWaliBimbingan::class, 'id_dosen_wali');
    }
}
