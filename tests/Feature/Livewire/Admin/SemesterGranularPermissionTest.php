<?php

use App\Livewire\Admin\Semester\Index;
use App\Models\Semester;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view semester but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $semester = Semester::factory()->create();

    $this->actingAs($admin)->get(route('admin.semester.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.semester.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.semester.edit', $semester->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $semester = Semester::factory()->create();

    $this->actingAs($admin)->get(route('admin.semester.index'))
        ->assertOk()
        ->assertDontSee('Tambah Semester')
        ->assertDontSee(route('admin.semester.edit', $semester->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $semester->id)
        ->assertStatus(403);

    expect(Semester::find($semester->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete semester once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create semester', 'update semester', 'delete semester']);
    $semester = Semester::factory()->create();

    $this->actingAs($admin)->get(route('admin.semester.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.semester.edit', $semester->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $semester->id)
        ->call('delete');

    expect(Semester::find($semester->id))->toBeNull();
});

it('still lets superadmin do everything on semester regardless of granular mode', function () {
    $admin = adminUser();
    $semester = Semester::factory()->create();

    $this->actingAs($admin)->get(route('admin.semester.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.semester.edit', $semester->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $semester->id)
        ->call('delete');

    expect(Semester::find($semester->id))->toBeNull();
});

it('still blocks keuangan-only admins from semester entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.semester.index'))->assertStatus(403);
});
