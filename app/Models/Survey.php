<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'survey';
    protected $fillable = [
        'nama',
        'kode',
        'id_semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_semester' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_active' => 'boolean',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}

