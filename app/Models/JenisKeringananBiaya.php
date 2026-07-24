<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisKeringananBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_keringanan_biaya';

    protected $fillable = [
        'nama',
        'is_persentase',
        'nominal',
        'is_active',
        'keterangan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'is_persentase' => 'boolean',
        'is_active' => 'boolean',
        'nominal' => 'decimal:2',
    ];
}
