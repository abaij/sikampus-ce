<?php

use App\Livewire\Admin\Pengguna\Show;
use App\Models\User;
use Livewire\Livewire;

/**
 * Regresi untuk bug: tab "Permission Langsung" di halaman detail pengguna dulu menyamakan
 * 'manage X' dengan 'view X' lewat groupPermissionName() — mencentang "Read" untuk resource
 * granular (mis. tagihan) yang seharusnya cuma 'view tagihan' malah bisa tersimpan sebagai
 * 'manage tagihan' (akses penuh, termasuk hapus), dan untuk resource yang memang cuma punya satu
 * permission (mis. pengguna) terlihat seolah "Read" saja padahal itu 'manage pengguna' utuh.
 */
beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

function permissionRowIndex(array $permissionForm, string $resource): int
{
    foreach ($permissionForm as $index => $row) {
        if ($row['resource'] === $resource) {
            return $index;
        }
    }

    throw new RuntimeException("Resource {$resource} tidak ditemukan di permissionForm.");
}

it('assigns the granular view permission (not manage) when read is checked for a granular resource', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('setTab', 'permission');

    $index = permissionRowIndex($component->get('permissionForm'), 'tagihan');

    $component->set("permissionForm.{$index}.read", true)
        ->call('savePermissions');

    $pengguna = $pengguna->fresh();
    expect($pengguna->can('view tagihan'))->toBeTrue();
    expect($pengguna->can('manage tagihan'))->toBeFalse();
    expect($pengguna->can('delete tagihan'))->toBeFalse();
});

it('marks role as a non-granular resource and assigns full manage access when its single checkbox is checked', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('setTab', 'permission');

    $form = $component->get('permissionForm');
    $index = permissionRowIndex($form, 'role');

    expect($form[$index]['mode'])->toBe('single');
    expect($form[$index]['hasWrite'])->toBeFalse();
    expect($form[$index]['hasDelete'])->toBeFalse();

    $component->set("permissionForm.{$index}.read", true)
        ->call('savePermissions');

    expect($pengguna->fresh()->can('manage role'))->toBeTrue();
});

it('locks read/write/delete together for a non-granular resource — checking any column grants full manage access', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('setTab', 'permission');

    $index = permissionRowIndex($component->get('permissionForm'), 'role');

    // 'write' dipaksa true secara langsung (UI normalnya tidak menampilkan checkbox ini untuk
    // resource non-granular) — savePermissions() tetap harus memberi 'manage role' utuh,
    // bukan diam-diam tidak menyimpan apa pun.
    $component->set("permissionForm.{$index}.write", true)
        ->call('savePermissions');

    expect($pengguna->fresh()->can('manage role'))->toBeTrue();
});

it('self-heals a stale manage-level grant on a now-granular resource once the form is saved', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $pengguna->givePermissionTo('manage tagihan');

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('setTab', 'permission');

    $form = $component->get('permissionForm');
    $index = permissionRowIndex($form, 'tagihan');

    // 'manage tagihan' tidak lagi dipetakan ke kolom Read untuk resource granular — form
    // ter-load dengan read=false meski user masih punya 'manage tagihan' secara langsung.
    expect($form[$index]['read'])->toBeFalse();

    $component->call('savePermissions');

    $pengguna = $pengguna->fresh();
    expect($pengguna->can('manage tagihan'))->toBeFalse();
    expect($pengguna->can('view tagihan'))->toBeFalse();
});

it('loads read as true when the user already has the granular view permission', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $pengguna->givePermissionTo('view tagihan');

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('setTab', 'permission');

    $form = $component->get('permissionForm');
    $index = permissionRowIndex($form, 'tagihan');

    expect($form[$index]['read'])->toBeTrue();
    expect($form[$index]['write'])->toBeFalse();
    expect($form[$index]['delete'])->toBeFalse();
});
