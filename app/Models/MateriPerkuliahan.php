<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MateriPerkuliahan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'materi_perkuliahan';

    protected $fillable = [
        'id_jadwal',
        'nama',
        'file',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_jadwal' => 'integer',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal');
    }
}
