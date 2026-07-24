<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusAkademik extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'status_akademik';
    protected $fillable = ['nama', 'deskripsi'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function statusMahasiswa()
    {
        return $this->hasMany(StatusMahasiswa::class, 'id_status_akademik');
    }
}
