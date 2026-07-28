<?php

use App\Livewire\Admin\Wisuda\Form;
use App\Livewire\Admin\Wisuda\Index;
use App\Livewire\Admin\Wisuda\Show;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;
use Livewire\Livewire;

/** Peserta wisuda hanya boleh mahasiswa yang sudah punya yudisium. */
function mahasiswaBeryudisium(array $attributes = []): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create($attributes);
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    return $mahasiswa;
}

it('renders index, create, edit and show pages', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create(['nama' => 'Wisuda Periode I 2026']);

    $this->actingAs($admin)->get(route('admin.akademik.wisuda'))->assertOk()->assertSee('Wisuda Periode I 2026');
    $this->actingAs($admin)->get(route('admin.akademik.wisuda.create'))->assertOk()->assertSee('Tambah Wisuda');
    $this->actingAs($admin)->get(route('admin.akademik.wisuda.edit', $wisuda->id))->assertOk()->assertSee('Ubah Wisuda');
    $this->actingAs($admin)->get(route('admin.akademik.wisuda.show', $wisuda->id))->assertOk()->assertSee('Peserta Wisuda');
});

it('creates, updates, and deletes a wisuda', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Wisuda Periode II')
        ->set('tanggal_wisuda', '2026-09-20')
        ->set('status', 'active')
        ->call('save')
        ->assertRedirect(route('admin.akademik.wisuda'));

    $wisuda = Wisuda::where('nama', 'Wisuda Periode II')->firstOrFail();
    expect($wisuda->status)->toBe('active');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $wisuda->id])
        ->assertSet('nama', 'Wisuda Periode II')
        ->set('nama', 'Wisuda Periode II (Revisi)')
        ->call('save');

    expect($wisuda->fresh()->nama)->toBe('Wisuda Periode II (Revisi)');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $wisuda->id)
        ->call('delete');

    expect(Wisuda::find($wisuda->id))->toBeNull();
});

it('rejects a duplicate nama and tanggal combination', function () {
    $admin = adminUser();
    Wisuda::factory()->create(['nama' => 'Wisuda Ganda', 'tanggal_wisuda' => '2026-10-10']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Wisuda Ganda')
        ->set('tanggal_wisuda', '2026-10-10')
        ->call('save')
        ->assertHasErrors('nama');

    expect(Wisuda::where('nama', 'Wisuda Ganda')->count())->toBe(1);
});

it('defaults the status to inactive when left blank', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Wisuda Tanpa Status')
        ->set('tanggal_wisuda', '2026-11-11')
        ->set('status', '')
        ->call('save');

    expect(Wisuda::where('nama', 'Wisuda Tanpa Status')->firstOrFail()->status)->toBe('inactive');
});

it('adds, updates, and removes a peserta', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();
    $mahasiswa = mahasiswaBeryudisium();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openTambahModal')
        ->call('selectMahasiswa', $mahasiswa->id)
        ->set('no_sk_wisuda', 'SKW/001/2026')
        ->call('savePeserta');

    $peserta = WisudaMahasiswa::where('id_wisuda', $wisuda->id)->firstOrFail();
    expect($peserta->id_mahasiswa)->toBe($mahasiswa->id);
    expect($peserta->status)->toBe('pending');

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openEditPeserta', $peserta->id)
        ->assertSet('no_sk_wisuda', 'SKW/001/2026')
        ->set('pesertaStatus', 'approved')
        ->call('saveEditPeserta');

    expect($peserta->fresh()->status)->toBe('approved');

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('confirmDeletePeserta', $peserta->id)
        ->call('deletePeserta');

    expect(WisudaMahasiswa::find($peserta->id))->toBeNull();
});

it('refuses a mahasiswa without yudisium and one already registered', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();

    $tanpaYudisium = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openTambahModal')
        ->call('selectMahasiswa', $tanpaYudisium->id)
        ->call('savePeserta')
        ->assertHasErrors('selectedMahasiswaId');

    expect(WisudaMahasiswa::where('id_wisuda', $wisuda->id)->count())->toBe(0);

    $sudahTerdaftar = mahasiswaBeryudisium();
    WisudaMahasiswa::create([
        'id_wisuda' => $wisuda->id,
        'id_mahasiswa' => $sudahTerdaftar->id,
        'status' => 'pending',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openTambahModal')
        ->call('selectMahasiswa', $sudahTerdaftar->id)
        ->call('savePeserta')
        ->assertHasErrors('selectedMahasiswaId');

    expect(WisudaMahasiswa::where('id_wisuda', $wisuda->id)->count())->toBe(1);
});

it('only offers eligible mahasiswa as calon peserta', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();

    $eligible = mahasiswaBeryudisium(['nama' => 'Calon Layak Wisuda']);
    Mahasiswa::factory()->create(['nama' => 'Tanpa Yudisium Sama Sekali']);

    $sudahTerdaftar = mahasiswaBeryudisium(['nama' => 'Sudah Terdaftar Wisuda']);
    WisudaMahasiswa::create([
        'id_wisuda' => $wisuda->id,
        'id_mahasiswa' => $sudahTerdaftar->id,
        'status' => 'pending',
    ]);

    $calon = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openTambahModal')
        ->get('calonPeserta')
        ->pluck('id');

    expect($calon)->toContain($eligible->id);
    expect($calon)->not->toContain($sudahTerdaftar->id);
});

it('restores a previously removed peserta instead of duplicating the row', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();
    $mahasiswa = mahasiswaBeryudisium();

    $peserta = WisudaMahasiswa::create([
        'id_wisuda' => $wisuda->id,
        'id_mahasiswa' => $mahasiswa->id,
        'status' => 'pending',
    ]);
    $peserta->delete();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openTambahModal')
        ->call('selectMahasiswa', $mahasiswa->id)
        ->set('no_sk_wisuda', 'SKW/RESTORED/2026')
        ->call('savePeserta');

    expect(WisudaMahasiswa::withTrashed()->where('id_wisuda', $wisuda->id)->count())->toBe(1);
    expect($peserta->fresh()->trashed())->toBeFalse();
    expect($peserta->fresh()->no_sk_wisuda)->toBe('SKW/RESTORED/2026');
});

it('streams a pdf and an excel peserta export', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();
    WisudaMahasiswa::create([
        'id_wisuda' => $wisuda->id,
        'id_mahasiswa' => mahasiswaBeryudisium()->id,
        'status' => 'approved',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('exportExcel')
        ->assertFileDownloaded(null, null, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('exportPdf')
        ->assertFileDownloaded(null, null, 'application/pdf');
});

// Regression: layouts.web me-render @section('page_actions') di luar root <div> komponen, jadi
// tombol wire:click yang diletakkan di sana tidak pernah terikat Livewire dan diam saja saat diklik.
it('keeps the peserta action buttons inside the livewire root', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.akademik.wisuda.show', $wisuda->id))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();

    foreach (['exportPdf', 'exportExcel', 'openTambahModal'] as $action) {
        expect(strpos($html, 'wire:click="'.$action.'"'))->toBeGreaterThan($rootStart);
    }
});

it('hides out-of-scope mahasiswa from calon peserta and blocks selecting them', function () {
    $admin = adminUser('admin_akademik');
    $allowedProdi = Prodi::factory()->create();
    scopeAdminToProdi($admin, $allowedProdi->id);

    $wisuda = Wisuda::factory()->create();
    $dalamScope = mahasiswaBeryudisium(['id_prodi' => $allowedProdi->id]);
    $luarScope = mahasiswaBeryudisium(['id_prodi' => Prodi::factory()->create()->id]);

    $calon = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('openTambahModal')
        ->get('calonPeserta')
        ->pluck('id');

    expect($calon)->toContain($dalamScope->id);
    expect($calon)->not->toContain($luarScope->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $wisuda->id])
        ->call('selectMahasiswa', $luarScope->id)
        ->assertStatus(403);
});

it('redirects unauthenticated users to the login page', function () {
    $wisuda = Wisuda::factory()->create();

    $this->get(route('admin.akademik.wisuda'))->assertRedirect(route('login'));
    $this->get(route('admin.akademik.wisuda.show', $wisuda->id))->assertRedirect(route('login'));
});
