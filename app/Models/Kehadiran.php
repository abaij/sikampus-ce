<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kehadiran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kehadiran';
    protected $fillable = [
        'id_perkuliahan',
        'id_mhs',
        'keterangan',
        'status',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_perkuliahan' => 'integer',
        'id_mhs' => 'integer',
    ];

    public function perkuliahan()
    {
        return $this->belongsTo(Perkuliahan::class, 'id_perkuliahan');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mhs');
    }
}

