<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class UserRoleScope extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_role_scopes';

    protected $fillable = [
        'id_user',
        'id_role',
        'id_scope',
        'scope_type',
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

    /**
     * Format baris scope ke payload yang sama dengan /auth/me (per role).
     *
     * @return array<string, mixed>|\stdClass
     */
    public static function buildScopesPayload(int $userId, int $roleId): array|\stdClass
    {
        $userRoleScopes = DB::table('user_role_scopes')
            ->where('id_user', $userId)
            ->where('id_role', $roleId)
            ->whereNull('deleted_at')
            ->get();

        $scopesByType = [];
        foreach ($userRoleScopes as $scope) {
            $scopeType = $scope->scope_type;
            if (! isset($scopesByType[$scopeType])) {
                $scopesByType[$scopeType] = [];
            }
            $scopesByType[$scopeType][] = (int) $scope->id_scope;
        }

        $scopes = [];
        foreach ($scopesByType as $scopeType => $ids) {
            if ($scopeType === 'kampus' && count($ids) === 1 && $ids[0] == 1) {
                $scopes[$scopeType] = true;
            } else {
                $scopes[$scopeType] = array_unique($ids);
            }
        }

        if (empty($scopes)) {
            return new \stdClass;
        }

        return $scopes;
    }
}
