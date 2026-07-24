<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentangNilai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rentang_nilai';
    protected $fillable = [
        'id_jenjang',
        'nilai_huruf',
        'nilai_angka',
        'nilai_rendah',
        'nilai_tinggi',
        'is_lulus',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_jenjang' => 'integer',
        'nilai_angka' => 'decimal:2',
        'nilai_rendah' => 'decimal:2',
        'nilai_tinggi' => 'decimal:2',
        'is_lulus' => 'boolean',
    ];

    public function jenjang()
    {
        return $this->belongsTo(Jenjang::class, 'id_jenjang');
    }
}
