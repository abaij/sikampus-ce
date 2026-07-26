<?php

use App\Livewire\Admin\JenisPenilaian\Form;
use App\Livewire\Admin\JenisPenilaian\Index;
use App\Models\JenisPenilaian;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    JenisPenilaian::factory()->create(['nama' => 'Ujian Tengah Semester']);

    $this->actingAs($admin)->get(route('admin.akademik.jenis-penilaian'))->assertOk()->assertSee('Ujian Tengah Semester');
    $this->actingAs($admin)->get(route('admin.akademik.jenis-penilaian.create'))->assertOk()->assertSee('Tambah Jenis Penilaian');
});

it('creates, updates, and deletes a jenis penilaian', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', 'UTS')
        ->set('nama', 'Ujian Tengah Semester')
        ->set('bobot', 30)
        ->set('status', 'manual')
        ->call('save')
        ->assertRedirect(route('admin.akademik.jenis-penilaian'));

    $jenisPenilaian = JenisPenilaian::where('kode', 'UTS')->firstOrFail();
    expect($jenisPenilaian->bobot)->toBe(30);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jenisPenilaian->id])
        ->assertSet('nama', 'Ujian Tengah Semester')
        ->assertSet('bobot', 30)
        ->set('nama', 'UTS Semester Ganjil')
        ->call('save');

    expect($jenisPenilaian->fresh()->nama)->toBe('UTS Semester Ganjil');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenisPenilaian->id)
        ->call('delete');

    expect(JenisPenilaian::find($jenisPenilaian->id))->toBeNull();
});

it('rejects a duplicate kode', function () {
    $admin = adminUser();
    JenisPenilaian::factory()->create(['kode' => 'UAS']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', 'UAS')
        ->set('nama', 'Ujian Akhir Semester Duplikat')
        ->call('save')
        ->assertHasErrors('kode');
});

it('rejects when total bobot across all jenis penilaian would exceed 100 percent', function () {
    $admin = adminUser();
    JenisPenilaian::factory()->create(['bobot' => 70]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', 'TUGAS')
        ->set('nama', 'Tugas')
        ->set('bobot', 40)
        ->call('save')
        ->assertHasErrors('bobot');

    expect(JenisPenilaian::where('kode', 'TUGAS')->exists())->toBeFalse();
});

it('allows updating a jenis penilaian bobot as long as the total excluding itself stays within 100 percent', function () {
    $admin = adminUser();
    $existing = JenisPenilaian::factory()->create(['bobot' => 30]);
    JenisPenilaian::factory()->create(['bobot' => 50]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $existing->id])
        ->set('bobot', 45)
        ->call('save')
        ->assertRedirect(route('admin.akademik.jenis-penilaian'));

    expect($existing->fresh()->bobot)->toBe(45);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.jenis-penilaian'))->assertRedirect(route('login'));
});
