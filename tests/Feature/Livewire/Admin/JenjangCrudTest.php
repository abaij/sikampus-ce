<?php

use App\Livewire\Admin\Jenjang\Form;
use App\Livewire\Admin\Jenjang\Index;
use App\Models\Jenjang;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    Jenjang::factory()->create(['nama' => 'Sarjana']);

    $this->actingAs($admin)->get(route('admin.jenjang.index'))->assertOk()->assertSee('Sarjana');
    $this->actingAs($admin)->get(route('admin.jenjang.create'))->assertOk()->assertSee('Tambah Jenjang');
});

it('creates, updates, and deletes a jenjang', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Magister')
        ->call('save')
        ->assertRedirect(route('admin.jenjang.index'));

    $jenjang = Jenjang::where('nama', 'Magister')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jenjang->id])
        ->assertSet('nama', 'Magister')
        ->set('nama', 'Magister Terapan')
        ->call('save');

    expect($jenjang->fresh()->nama)->toBe('Magister Terapan');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenjang->id)
        ->call('delete');

    expect(Jenjang::find($jenjang->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.jenjang.index'))->assertRedirect(route('login'));
});
