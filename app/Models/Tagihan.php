<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tagihan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tagihan';
    protected $fillable = ['id_mahasiswa', 'id_semester', 'no_tagihan', 'total', 'status', 'tanggal_tagihan', 'tanggal_jatuh_tempo', 'tanggal_pembayaran', 'keterangan'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_mahasiswa' => 'integer',
        'id_semester' => 'integer',
        'no_tagihan' => 'string',
        'total' => 'decimal:2',
        'tanggal_tagihan' => 'datetime',
        'tanggal_jatuh_tempo' => 'datetime',
        'tanggal_pembayaran' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function tagihanRinci()
    {
        return $this->hasMany(TagihanRinci::class, 'id_tagihan');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'id_tagihan');
    }
}
