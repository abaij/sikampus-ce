<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';

    protected $fillable = [
        'id_user',
        'tipe',
        'judul',
        'pesan',
        'url',
        'dibaca_pada',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'dibaca_pada' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->whereNull('dibaca_pada');
    }

    /**
     * Buat satu notifikasi untuk seorang user. Titik pemanggilan tunggal supaya format
     * tipe/judul/pesan konsisten di semua tempat yang memicu notifikasi.
     */
    public static function kirim(int $idUser, string $tipe, string $judul, string $pesan, ?string $url = null): self
    {
        return static::create([
            'id_user' => $idUser,
            'tipe' => $tipe,
            'judul' => $judul,
            'pesan' => $pesan,
            'url' => $url,
        ]);
    }
}
