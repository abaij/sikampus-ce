<?php

use App\Livewire\Admin\PerguruanTinggi;
use App\Models\Setting;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view the perguruan tinggi page but hides the simpan button', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.perguruan-tinggi'))
        ->assertOk()
        ->assertDontSee('Simpan');
});

it('blocks a view-only akademik admin from saving via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('nama', 'Universitas Uji')
        ->call('save')
        ->assertStatus(403);
});

it('lets an akademik admin save once granted the specific permission', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo('update perguruan tinggi');

    $this->actingAs($admin)->get(route('admin.perguruan-tinggi'))
        ->assertOk()
        ->assertSee('Simpan');

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('nama', 'Universitas Uji')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_univ_name')->value('value'))->toBe('Universitas Uji');
});

it('still lets superadmin save regardless of granular mode', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('nama', 'Universitas Uji Super')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_univ_name')->value('value'))->toBe('Universitas Uji Super');
});

it('still blocks keuangan-only admins from the perguruan tinggi page entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.perguruan-tinggi'))->assertStatus(403);
});
