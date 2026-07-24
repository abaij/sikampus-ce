<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agama extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agama';
    protected $fillable = ['nama', 'status'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
