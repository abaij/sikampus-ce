<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penghasilan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'penghasilan';
    protected $fillable = ['nama'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
