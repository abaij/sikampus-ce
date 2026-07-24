<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbCamaba extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_camaba';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_user',
        'nama',
        'email',
        'nim',
        'status_herregistrasi',
        'id_kota_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'no_hp',
        'no_wa',
        'alamat',
        'kode_pos',
        'rt',
        'rw',
        'dusun',
        'kelurahan',
        'id_kota',
        'id_kecamatan',
        'id_provinsi',
        'id_negara',
        'foto',
        'no_ktp',
        'no_kk',
        'no_npwp',
        'no_sim',
        'no_kps',
        'nama_ayah',
        'nama_ibu',
        'nama_wali',
        'no_hp_ayah',
        'no_hp_ibu',
        'no_hp_wali',
        'alamat_ayah',
        'alamat_ibu',
        'alamat_wali',
        'id_agama',
        'status_perkawinan',
        'kewarganegaraan',
        'asal_sekolah',
        'nisn',
        'npsn',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }

    /**
     * Get the user that owns the camaba.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(PmbUser::class, 'id_user');
    }

    public function kotaLahir(): BelongsTo
    {
        return $this->belongsTo(Kota::class, 'id_kota_lahir');
    }

    public function kota(): BelongsTo
    {
        return $this->belongsTo(Kota::class, 'id_kota');
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class, 'id_kecamatan');
    }

    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class, 'id_provinsi');
    }

    public function negara(): BelongsTo
    {
        return $this->belongsTo(Negara::class, 'id_negara');
    }

    public function agama(): BelongsTo
    {
        return $this->belongsTo(Agama::class, 'id_agama');
    }

    /**
     * Semua baris pendaftaran PMB untuk camaba ini (filter periode di query eager load).
     */
    public function pendaftarans(): HasMany
    {
        return $this->hasMany(PmbPendaftaran::class, 'id_camaba');
    }

    /**
     * Log email kontak admin ke camaba (form kontak detail camaba / detail pendaftar).
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(PmbEmailLog::class, 'id_camaba');
    }
}
