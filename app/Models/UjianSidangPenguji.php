<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UjianSidangPenguji extends Model
{
    use SoftDeletes;

    protected $table = 'ujian_sidang_penguji';

    protected $fillable = [
        'id_ujian_sidang',
        'id_dosen',
        'is_ketua',
        'catatan',
        'nilai',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id_ujian_sidang' => 'integer',
        'id_dosen' => 'integer',
        'is_ketua' => 'boolean',
        'nilai' => 'decimal:2',
    ];

    public function ujianSidang(): BelongsTo
    {
        return $this->belongsTo(UjianSidang::class, 'id_ujian_sidang');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }
}
