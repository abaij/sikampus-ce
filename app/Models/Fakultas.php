<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Dosen;

class Fakultas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fakultas';
    protected $fillable = ['nama', 'kode', 'deskripsi', 'website', 'email', 'telepon', 'alamat', 'kota', 'provinsi', 'kode_pos', 'negara', 'id_dekan', 'status'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_dekan' => 'integer',
        'status' => 'string',
    ];

    public function dekan()
    {
        return $this->belongsTo(Dosen::class, 'id_dekan');
    }
}
