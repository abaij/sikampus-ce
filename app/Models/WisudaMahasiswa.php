<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class WisudaMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wisuda_mahasiswa';

    protected $fillable = [
        'id_mahasiswa',
        'id_wisuda',
        'no_sk_wisuda',
        'tanggal_sk_wisuda',
        'file_sk_wisuda',
        'foto',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['deleted_at', 'deleted_by'];

    public function wisuda()
    {
        return $this->belongsTo(Wisuda::class, 'id_wisuda');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }
}
