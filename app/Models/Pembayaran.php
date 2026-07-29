<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pembayaran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pembayaran';

    protected $fillable = [
        'id_tagihan',
        'no_pembayaran',
        'nominal',
        'tanggal_pembayaran',
        'metode_pembayaran',
        'bukti_bayar',
        'keterangan',
        'approved_at',
        'approved_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_tagihan' => 'integer',
        'nominal' => 'decimal:2',
        'tanggal_pembayaran' => 'datetime',
        'approved_at' => 'datetime',
        'approved_by' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];

    public function tagihan()
    {
        return $this->belongsTo(Tagihan::class, 'id_tagihan');
    }

    /** Hanya pembayaran yang sudah disetujui yang mengurangi sisa tagihan. */
    public static function approvedQueryForTagihan(int $tagihanId)
    {
        return static::query()
            ->where('id_tagihan', $tagihanId)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_at');
    }

    /**
     * Versi SQL dari approvedQueryForTagihan()->sum('nominal'), sebagai subquery berkorelasi
     * terhadap satu baris tagihan. Dipakai query daftar/filter/agregat yang tidak bisa memuat
     * seluruh barisnya ke PHP dulu.
     */
    public static function sqlSumDisetujui(string $aliasTagihan = 'tagihan'): string
    {
        return "(SELECT COALESCE(SUM(pby.nominal), 0) FROM pembayaran pby
            WHERE pby.id_tagihan = {$aliasTagihan}.id
              AND pby.deleted_at IS NULL
              AND pby.approved_at IS NOT NULL)";
    }
}
