<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KomponenBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'komponen_biaya';

    protected $fillable = ['kode', 'nama', 'is_per_semester', 'is_akademik'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'is_per_semester' => 'boolean',
        'is_akademik' => 'boolean',
    ];
}
