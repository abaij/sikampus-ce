<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AturanAksesKeuangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'aturan_akses_keuangan';

    protected $fillable = [
        'kode_akses',
        'nama',
        'persentase_minimum',
        'status',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'persentase_minimum' => 'decimal:2',
    ];
}
