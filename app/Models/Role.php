<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'code',
        'name',
        'guard_name',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    
    protected $hidden = ['created_at', 'updated_at'];
}

