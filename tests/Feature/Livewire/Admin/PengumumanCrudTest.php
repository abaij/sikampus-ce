<?php

use App\Livewire\Admin\Pengumuman\Form;
use App\Livewire\Admin\Pengumuman\Index;
use App\Models\Pengumuman;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    Pengumuman::factory()->create(['judul' => 'Libur Semester Genap']);

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman'))->assertOk()->assertSee('Libur Semester Genap');
    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.create'))->assertOk()->assertSee('Tambah Pengumuman');
});

it('creates, updates, and deletes a pengumuman', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('judul', 'Pengumuman Baru')
        ->set('isi', 'Isi pengumuman baru')
        ->set('audien', 'mahasiswa')
        ->set('prioritas', 'high')
        ->set('tanggal_mulai', '2026-08-01T08:00')
        ->set('tanggal_selesai', '2026-08-10T17:00')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.pengumuman'));

    $pengumuman = Pengumuman::where('judul', 'Pengumuman Baru')->firstOrFail();
    expect($pengumuman->audien)->toBe('mahasiswa');
    expect($pengumuman->prioritas)->toBe('high');
    expect($pengumuman->created_by)->toBe((string) $admin->id);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengumuman->id])
        ->assertSet('judul', 'Pengumuman Baru')
        ->set('judul', 'Pengumuman Diubah')
        ->call('save');

    expect($pengumuman->fresh()->judul)->toBe('Pengumuman Diubah');
    expect($pengumuman->fresh()->updated_by)->toBe((string) $admin->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $pengumuman->id)
        ->call('delete');

    expect(Pengumuman::find($pengumuman->id))->toBeNull();
});

it('rejects an invalid audien and a tanggal_selesai before tanggal_mulai', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('judul', 'Pengumuman Salah')
        ->set('isi', 'Isi')
        ->set('audien', 'bukan_audien_valid')
        ->call('save')
        ->assertHasErrors('audien');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('judul', 'Pengumuman Salah Tanggal')
        ->set('isi', 'Isi')
        ->set('tanggal_mulai', '2026-08-10T08:00')
        ->set('tanggal_selesai', '2026-08-01T08:00')
        ->call('save')
        ->assertHasErrors('tanggal_selesai');
});

it('filters pengumuman by search, audien, prioritas, and status', function () {
    $admin = adminUser();
    Pengumuman::factory()->create(['judul' => 'Info KRS', 'audien' => 'mahasiswa', 'prioritas' => 'high']);
    Pengumuman::factory()->create(['judul' => 'Info Gaji', 'audien' => 'staff', 'prioritas' => 'low']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'KRS')
        ->assertSee('Info KRS')
        ->assertDontSee('Info Gaji');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', '')
        ->set('filterAudien', 'staff')
        ->assertSee('Info Gaji')
        ->assertDontSee('Info KRS');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterPrioritas', 'high')
        ->assertSee('Info KRS')
        ->assertDontSee('Info Gaji');
});

it('carries the current page/search state from index into the Ubah link', function () {
    $admin = adminUser();
    Pengumuman::factory()->count(15)->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee('page=2');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $pengumuman = Pengumuman::factory()->create();

    $expectedBackUrl = route('admin.administrasi.pengumuman').'?page=2&search=krs';

    $this->actingAs($admin)
        ->get(route('admin.administrasi.pengumuman.edit', $pengumuman->id).'?page=2&search=krs&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'krs'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $pengumuman->id])
        ->set('judul', 'Pengumuman Update')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('keeps the index action buttons inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    $pengumuman = Pengumuman::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.administrasi.pengumuman'))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();
    expect(strpos($html, "wire:click=\"confirmDelete({$pengumuman->id})\""))->toBeGreaterThan($rootStart);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.administrasi.pengumuman'))->assertRedirect(route('login'));
});
