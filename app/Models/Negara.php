<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Negara extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'negara';

    protected $fillable = ['nama', 'kode'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
