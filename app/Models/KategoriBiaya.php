<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kategori_biaya';
    protected $fillable = ['nama', 'kode', 'status'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'status' => 'string',
    ];

    public function kategori_biaya_mahasiswa()
    {
        return $this->hasMany(KategoriBiayaMahasiswa::class, 'id_kategori_biaya', 'id');
    }
}
