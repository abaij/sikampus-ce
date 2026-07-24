<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class KeringananBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'keringanan_biaya';

    protected $fillable = [
        'id_jenis_keringanan_biaya',
        'id_mahasiswa',
        'id_semester',
        'id_aturan_akses_keuangan',
        'nominal',
        'keterangan',
        'file_lampiran',
        'status',
        'tanggal_pengajuan',
        'tanggal_approved',
        'approved_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['deleted_at'];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_pengajuan' => 'datetime',
        'tanggal_approved' => 'datetime',
    ];

    protected $appends = ['file_lampiran_url'];

    public function getFileLampiranUrlAttribute(): ?string
    {
        if (empty($this->file_lampiran)) {
            return null;
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base.'/storage/'.ltrim($this->file_lampiran, '/');
    }

    public function jenisKeringananBiaya(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JenisKeringananBiaya::class, 'id_jenis_keringanan_biaya');
    }

    public function mahasiswa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function aturanAksesKeuangan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AturanAksesKeuangan::class, 'id_aturan_akses_keuangan');
    }
}
