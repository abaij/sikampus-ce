<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ktm extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ktm';

    protected $fillable = [
        'id_mahasiswa',
        'nomor_ktm',
        'file',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    protected $casts = [
        'id_mahasiswa' => 'integer',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }
}
