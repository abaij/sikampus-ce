<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Krs extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'krs';

    protected $fillable = ['id_mahasiswa', 'id_kelas', 'approved_by', 'approved_at'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_mahasiswa' => 'integer',
        'id_kelas' => 'integer',
        'approved_by' => 'string',
        'approved_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa', 'id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id');
    }
}
