<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbDaftarUlang extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pmb_daftar_ulang';

    protected $fillable = [
        'id_pendaftaran',
        'id_prodi',
        'tanggal_daftar_ulang',
        'status',
        'file_bukti_bayar',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_daftar_ulang' => 'date',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftaran::class, 'id_pendaftaran');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    /**
     * Status daftar ulang untuk API/UI: prioritas dari `pmb_camaba.status_herregistrasi`,
     * lalu kolom `pmb_daftar_ulang.status` (mis. `acc`).
     *
     * Nilai `acc` pada camaba atau pada baris daftar ulang harus tampil sebagai `acc`, bukan disamakan dengan herregistrasi.
     */
    public function getStatusAttribute(?string $value): string
    {
        $camaba = $this->pendaftaran?->camaba;
        if ($camaba !== null) {
            $h = $camaba->status_herregistrasi;
            if ($h === 'acc') {
                return 'acc';
            }
            if ($h === 'herregistrasi' || $h === 'pending') {
                return $h;
            }
        }

        if (($value ?? '') === 'acc') {
            return 'acc';
        }

        return ($value !== null && $value !== '') ? $value : 'pending';
    }
}
