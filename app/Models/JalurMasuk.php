<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JalurMasuk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jalur_masuk';
    protected $fillable = [
        'nama',
        'deskripsi',
        'is_free_of_charge',
        'has_selection',
        'has_interview',
        'has_physical_test',
        'has_psychological_test',
        'has_medical_test',
        'status',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'is_free_of_charge' => 'boolean',
        'has_selection' => 'boolean',
        'has_interview' => 'boolean',
        'has_physical_test' => 'boolean',
        'has_psychological_test' => 'boolean',
        'has_medical_test' => 'boolean',
    ];
}

