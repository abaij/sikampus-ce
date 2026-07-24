<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatkulPrasyarat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'matkul_prasyarat';

    protected $fillable = [
        'id_matkul',
        'id_matkul_prasyarat',
    ];

    protected $casts = [
        'id_matkul' => 'integer',
        'id_matkul_prasyarat' => 'integer',
    ];

    public function matkulInduk()
    {
        return $this->belongsTo(Matkul::class, 'id_matkul');
    }

    /**
     * Mata kuliah yang menjadi prasyarat.
     */
    public function matkulPrasyarat()
    {
        return $this->belongsTo(Matkul::class, 'id_matkul_prasyarat');
    }
}
