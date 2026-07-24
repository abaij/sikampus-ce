<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wisuda extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wisuda';

    protected $fillable = [
        'nama',
        'tanggal_wisuda',
        'keterangan',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['deleted_at', 'deleted_by'];

    protected $casts = [
        'tanggal_wisuda' => 'date',
    ];

    public function wisudaMahasiswa()
    {
        return $this->hasMany(WisudaMahasiswa::class, 'id_wisuda');
    }
}
