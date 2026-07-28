<?php

use App\Livewire\Admin\AturanAksesKeuangan\Form;
use App\Livewire\Admin\AturanAksesKeuangan\Index;
use App\Models\AturanAksesKeuangan;
use App\Models\KeringananBiaya;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'nama' => 'Pengisian KRS']);

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan'))->assertOk()->assertSee('Pengisian KRS');
    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.create'))->assertOk()->assertSee('Tambah Aturan Akses Keuangan');
});

it('creates an aturan akses keuangan and lowercases the kode akses as typed', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode_akses', 'UAS_Semester')
        ->assertSet('kode_akses', 'uas_semester')
        ->set('nama', 'Ujian Akhir Semester')
        ->set('persentase_minimum', '80')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.aturan-akses-keuangan'));

    $row = AturanAksesKeuangan::where('kode_akses', 'uas_semester')->firstOrFail();
    expect($row->nama)->toBe('Ujian Akhir Semester');
    expect((float) $row->persentase_minimum)->toBe(80.0);
    expect($row->status)->toBe('active');
});

it('defaults persentase_minimum to null when left empty', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode_akses', 'tanpa_syarat')
        ->call('save');

    $row = AturanAksesKeuangan::where('kode_akses', 'tanpa_syarat')->firstOrFail();
    expect($row->persentase_minimum)->toBeNull();
});

it('rejects a kode_akses with invalid characters', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode_akses', 'Kode Dengan Spasi!')
        ->call('save')
        ->assertHasErrors('kode_akses');
});

it('rejects a duplicate kode_akses', function () {
    $admin = adminUser();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode_akses', 'krs')
        ->call('save')
        ->assertHasErrors('kode_akses');
});

it('rejects a persentase_minimum above 100', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode_akses', 'invalid_persen')
        ->set('persentase_minimum', '150')
        ->call('save')
        ->assertHasErrors('persentase_minimum');
});

it('updates an aturan akses keuangan, allowing its own kode_akses to stay unchanged', function () {
    $admin = adminUser();
    $row = AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $row->id])
        ->assertSet('kode_akses', 'krs')
        ->set('status', 'inactive')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.aturan-akses-keuangan'));

    expect($row->fresh()->status)->toBe('inactive');
});

it('deletes an aturan akses keuangan that is not in use', function () {
    $admin = adminUser();
    $row = AturanAksesKeuangan::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $row->id)
        ->call('delete');

    expect(AturanAksesKeuangan::find($row->id))->toBeNull();
});

it('searches by kode_akses or nama', function () {
    $admin = adminUser();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'findable_target', 'nama' => 'Findable']);
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'lain_sama_sekali', 'nama' => 'Lain']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'findable')
        ->assertSee('findable_target')
        ->assertDontSee('lain_sama_sekali');
});

it('soft-deletes an aturan still referenced by keringanan biaya without a usage guard, matching the API having none', function () {
    // AturanAksesKeuangan uses SoftDeletes, sehingga delete() tidak memicu FK
    // restrictOnDelete di tabel keringanan_biaya (baris tidak benar-benar dihapus dari DB) —
    // controller aslinya juga tidak melakukan pengecekan pemakaian sebelum menghapus.
    $admin = adminUser();
    $row = AturanAksesKeuangan::factory()->create();
    KeringananBiaya::factory()->create(['id_aturan_akses_keuangan' => $row->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $row->id)
        ->call('delete');

    expect(AturanAksesKeuangan::find($row->id))->toBeNull();
    expect(AturanAksesKeuangan::withTrashed()->find($row->id))->not->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.aturan-akses-keuangan'))->assertRedirect(route('login'));
});
