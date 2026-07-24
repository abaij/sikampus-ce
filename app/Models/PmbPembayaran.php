<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbPembayaran extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_pembayaran';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_pendaftaran',
        'id_biaya',
        'no_kuitansi',
        'jumlah',
        'status',
        'keterangan',
        'tanggal_pembayaran',
        'file',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal_pembayaran' => 'datetime',
        ];
    }

    /**
     * Get the pendaftaran that owns the pembayaran.
     */
    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftaran::class, 'id_pendaftaran');
    }

    /**
     * Get the biaya that owns the pembayaran.
     */
    public function biaya(): BelongsTo
    {
        return $this->belongsTo(PmbBiaya::class, 'id_biaya');
    }
}

