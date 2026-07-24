<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbPendaftaran extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_pendaftaran';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_camaba',
        'id_periode',
        'tanggal_pendaftaran',
        'no_pendaftaran',
        'status',
        'keterangan',
        'id_jalur_masuk',
        'id_jenis_daftar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_pendaftaran' => 'date',
        ];
    }

    /**
     * Get the camaba that owns the pendaftaran.
     */
    public function camaba(): BelongsTo
    {
        return $this->belongsTo(PmbCamaba::class, 'id_camaba');
    }

    /**
     * Get the periode that owns the pendaftaran.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PmbPeriode::class, 'id_periode');
    }

    /**
     * Get the jalur masuk that owns the pendaftaran.
     */
    public function jalurMasuk(): BelongsTo
    {
        return $this->belongsTo(JalurMasuk::class, 'id_jalur_masuk');
    }

    /**
     * Get the jenis daftar that owns the pendaftaran.
     */
    public function jenisDaftar(): BelongsTo
    {
        return $this->belongsTo(JenisDaftar::class, 'id_jenis_daftar');
    }

    /**
     * Get the prodi pilihan for this pendaftaran.
     */
    public function prodiPilih(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PmbProdiPilih::class, 'id_pendaftaran');
    }

    /**
     * Get the pembayaran for this pendaftaran.
     */
    public function pembayaran(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PmbPembayaran::class, 'id_pendaftaran');
    }

    public function nilaiTes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PmbNilaiTes::class, 'id_pendaftaran');
    }

    public function hasilSeleksi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PmbHasilSeleksi::class, 'id_pendaftaran');
    }

    public function daftarUlang(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PmbDaftarUlang::class, 'id_pendaftaran');
    }
}

