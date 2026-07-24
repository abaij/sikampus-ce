<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbJenisTes extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pmb_jenis_tes';

    protected $fillable = [
        'nama',
        'keterangan',
        'is_aktif',
        'is_wajib',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'is_wajib' => 'boolean',
        ];
    }
}
