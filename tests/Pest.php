<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Buat user admin panel untuk test, lengkap dengan role Spatie yang sesuai
 * (dibuat otomatis kalau belum ada). Sejak Spatie menjadi satu-satunya sumber
 * kebenaran untuk role.admin/role.admin.keuangan, users.role legacy saja tidak lagi cukup.
 */
function adminUser(string $legacyRole = 'admin'): \App\Models\User
{
    $spatieRoleName = match ($legacyRole) {
        'admin_akademik' => 'Akademik',
        'admin_keuangan' => 'Keuangan',
        default => 'Superadmin',
    };

    $role = \App\Models\Role::firstOrCreate(
        ['name' => $spatieRoleName, 'guard_name' => 'web'],
        ['code' => strtolower($spatieRoleName)]
    );

    $user = \App\Models\User::factory()->create(['role' => $legacyRole]);
    $user->syncRoles([$role]);

    return $user;
}

/**
 * Beri user scope prodi langsung (user_role_scopes, scope_type=prodi) untuk role Spatie-nya.
 */
function scopeAdminToProdi(\App\Models\User $user, int $prodiId): void
{
    $role = $user->roles()->first();

    \Illuminate\Support\Facades\DB::table('user_role_scopes')->insert([
        'id_user' => $user->id,
        'id_role' => $role->id,
        'id_scope' => $prodiId,
        'scope_type' => 'prodi',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Beri user scope fakultas langsung (user_role_scopes, scope_type=fakultas) untuk role Spatie-nya.
 */
function scopeAdminToFakultas(\App\Models\User $user, int $fakultasId): void
{
    $role = $user->roles()->first();

    \Illuminate\Support\Facades\DB::table('user_role_scopes')->insert([
        'id_user' => $user->id,
        'id_role' => $role->id,
        'id_scope' => $fakultasId,
        'scope_type' => 'fakultas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
