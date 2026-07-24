<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dosen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dosen';
    protected $fillable = [
        'id_user',
        'nama',
        'kode_dosen',
        'email',
        'nip',
        'nidn',
        'gelar_depan',
        'gelar_belakang',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'status_perkawinan',
        'kewarganegaraan',
        'no_hp',
        'alamat',
        'kode_pos',
        'id_kota',
        'id_provinsi',
        'id_negara',
        'foto',
        'nidk',
        'nupn',
        'kuota_bimbingan_akademik',
        'kuota_bimbingan_ta',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'tanggal_lahir' => 'date',
        'kuota_bimbingan_akademik' => 'integer',
        'kuota_bimbingan_ta' => 'integer',
    ];

    public function mahasiswaBimbingan()
    {
        return $this->hasMany(DosenWali::class, 'id_dosen');
    }

    /** Prodi yang diketuai dosen sebagai kaprodi */
    public function prodiAsKaprodi()
    {
        return $this->hasOne(Prodi::class, 'id_kaprodi');
    }

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_provinsi');
    }

    public function kota()
    {
        return $this->belongsTo(Kota::class, 'id_kota');
    }

    public function negara()
    {
        return $this->belongsTo(Negara::class, 'id_negara');
    }
}
