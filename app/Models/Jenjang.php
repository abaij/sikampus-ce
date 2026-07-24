<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jenjang extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenjang';
    protected $fillable = ['kode', 'nama', 'deskripsi'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function rentangNilai()
    {
        return $this->hasMany(RentangNilai::class, 'id_jenjang')->orderBy('nilai_huruf', 'asc');
    }
}

