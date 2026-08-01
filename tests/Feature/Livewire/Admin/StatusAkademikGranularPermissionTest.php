<?php

use App\Livewire\Admin\StatusAkademik\Index;
use App\Models\StatusAkademik;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view status akademik but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $statusAkademik = StatusAkademik::factory()->create();

    $this->actingAs($admin)->get(route('admin.status-akademik.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.status-akademik.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.status-akademik.edit', $statusAkademik->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $statusAkademik = StatusAkademik::factory()->create();

    $this->actingAs($admin)->get(route('admin.status-akademik.index'))
        ->assertOk()
        ->assertDontSee('Tambah Status Akademik')
        ->assertDontSee(route('admin.status-akademik.edit', $statusAkademik->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $statusAkademik = StatusAkademik::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $statusAkademik->id)
        ->assertStatus(403);

    expect(StatusAkademik::find($statusAkademik->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete status akademik once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create status akademik', 'update status akademik', 'delete status akademik']);
    $statusAkademik = StatusAkademik::factory()->create();

    $this->actingAs($admin)->get(route('admin.status-akademik.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.status-akademik.edit', $statusAkademik->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $statusAkademik->id)
        ->call('delete');

    expect(StatusAkademik::find($statusAkademik->id))->toBeNull();
});

it('still lets superadmin do everything on status akademik regardless of granular mode', function () {
    $admin = adminUser();
    $statusAkademik = StatusAkademik::factory()->create();

    $this->actingAs($admin)->get(route('admin.status-akademik.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.status-akademik.edit', $statusAkademik->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $statusAkademik->id)
        ->call('delete');

    expect(StatusAkademik::find($statusAkademik->id))->toBeNull();
});

it('still blocks keuangan-only admins from status akademik entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.status-akademik.index'))->assertStatus(403);
});
