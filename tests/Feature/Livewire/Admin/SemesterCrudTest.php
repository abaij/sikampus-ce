<?php

use App\Livewire\Admin\Semester\Form;
use App\Livewire\Admin\Semester\Index;
use App\Models\Semester;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    Semester::factory()->create(['kode' => '20241', 'nama' => 'Ganjil 2024/2025']);

    $this->actingAs($admin)->get(route('admin.semester.index'))->assertOk()->assertSee('Ganjil 2024/2025');
    $this->actingAs($admin)->get(route('admin.semester.create'))->assertOk()->assertSee('Tambah Semester');
});

it('creates, updates, and deletes a semester', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', '20251')
        ->set('nama', 'Ganjil 2025/2026')
        ->call('save')
        ->assertRedirect(route('admin.semester.index'));

    $semester = Semester::where('kode', '20251')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $semester->id])
        ->assertSet('nama', 'Ganjil 2025/2026')
        ->set('nama', 'Ganjil 2025/2026 (Revisi)')
        ->call('save');

    expect($semester->fresh()->nama)->toBe('Ganjil 2025/2026 (Revisi)');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $semester->id)
        ->call('delete');

    expect(Semester::find($semester->id))->toBeNull();
});

it('deactivates the previously active semester when a new one is marked active', function () {
    $admin = adminUser();
    $active = Semester::factory()->active()->create(['kode' => '20241']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', '20242')
        ->set('nama', 'Genap 2024/2025')
        ->set('is_active', true)
        ->call('save')
        ->assertRedirect(route('admin.semester.index'));

    expect($active->fresh()->is_active)->toBeFalse();
    expect(Semester::where('kode', '20242')->firstOrFail()->is_active)->toBeTrue();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.semester.index'))->assertRedirect(route('login'));
});
