<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbDokumen extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_dokumen';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_pendaftaran',
        'id_persyaratan',
        'nama',
        'keterangan',
        'file',
        'status',
        'tanggal_upload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_upload' => 'datetime',
        ];
    }

    /**
     * Get the pendaftaran that owns the dokumen.
     */
    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftaran::class, 'id_pendaftaran');
    }

    /**
     * Get the persyaratan that owns the dokumen.
     */
    public function persyaratan(): BelongsTo
    {
        return $this->belongsTo(PmbPersyaratan::class, 'id_persyaratan');
    }
}

