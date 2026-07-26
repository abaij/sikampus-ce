<?php

use App\Livewire\Admin\StatusAkademik\Form;
use App\Livewire\Admin\StatusAkademik\Index;
use App\Models\Mahasiswa;
use App\Models\StatusAkademik;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    StatusAkademik::factory()->create(['nama' => 'Aktif']);

    $this->actingAs($admin)->get(route('admin.status-akademik.index'))->assertOk()->assertSee('Aktif');
    $this->actingAs($admin)->get(route('admin.status-akademik.create'))->assertOk()->assertSee('Tambah Status Akademik');
});

it('creates, updates, and deletes a status akademik', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Cuti')
        ->set('deskripsi', 'Mahasiswa sedang cuti akademik')
        ->call('save')
        ->assertRedirect(route('admin.status-akademik.index'));

    $statusAkademik = StatusAkademik::where('nama', 'Cuti')->firstOrFail();
    expect($statusAkademik->deskripsi)->toBe('Mahasiswa sedang cuti akademik');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $statusAkademik->id])
        ->assertSet('nama', 'Cuti')
        ->set('nama', 'Cuti Akademik')
        ->call('save');

    expect($statusAkademik->fresh()->nama)->toBe('Cuti Akademik');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $statusAkademik->id)
        ->call('delete');

    expect(StatusAkademik::find($statusAkademik->id))->toBeNull();
});

it('rejects duplicate nama', function () {
    $admin = adminUser();
    StatusAkademik::factory()->create(['nama' => 'Aktif']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Aktif')
        ->call('save')
        ->assertHasErrors(['nama']);
});

it('refuses to delete a status akademik still used by mahasiswa', function () {
    $admin = adminUser();
    $statusAkademik = StatusAkademik::factory()->create(['nama' => 'Lulus']);
    Mahasiswa::factory()->create(['id_status_akademik' => $statusAkademik->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $statusAkademik->id)
        ->call('delete')
        ->assertSet('deleteError', 'Status akademik tidak dapat dihapus karena masih dipakai oleh data mahasiswa.');

    expect(StatusAkademik::find($statusAkademik->id))->not->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.status-akademik.index'))->assertRedirect(route('login'));
});
