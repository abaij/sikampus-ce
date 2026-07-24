<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_roles';

    protected $fillable = [
        'id_user',
        'id_role',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function scopes()
    {
        return $this->hasMany(UserRoleScope::class, 'id_user', 'id_user')
            ->whereColumn('user_role_scopes.id_role', 'user_roles.id_role');
    }
}
