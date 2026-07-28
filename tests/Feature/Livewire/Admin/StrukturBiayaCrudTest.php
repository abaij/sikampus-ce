<?php

use App\Livewire\Admin\StrukturBiaya\Form;
use App\Livewire\Admin\StrukturBiaya\Index;
use App\Models\KategoriBiaya;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    $kategoriBiaya = KategoriBiaya::factory()->create(['nama' => 'Beasiswa Prestasi']);
    StrukturBiaya::factory()->create(['id_kategori_biaya' => $kategoriBiaya->id]);

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya'))->assertOk()->assertSee('Beasiswa Prestasi');
    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.create'))->assertOk()->assertSee('Tambah Struktur Biaya');
});

it('creates, updates, and deletes a struktur biaya', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_angkatan', $angkatan->id)
        ->set('id_periode', $periode->id)
        ->set('nominal', '5000000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.struktur-biaya'));

    $strukturBiaya = StrukturBiaya::where('id_angkatan', $angkatan->id)->where('id_periode', $periode->id)->firstOrFail();
    expect((float) $strukturBiaya->nominal)->toBe(5000000.0);
    expect($strukturBiaya->tahap)->toBe(1);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $strukturBiaya->id])
        ->assertSet('id_angkatan', $angkatan->id)
        ->set('nominal', '6000000')
        ->call('save');

    expect((float) $strukturBiaya->fresh()->nominal)->toBe(6000000.0);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $strukturBiaya->id)
        ->call('delete');

    expect(StrukturBiaya::find($strukturBiaya->id))->toBeNull();
});

it('defaults tahap to 1 when left blank', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_angkatan', $angkatan->id)
        ->set('id_periode', $periode->id)
        ->set('tahap', '')
        ->set('nominal', '1000')
        ->call('save')
        ->assertHasNoErrors();

    $strukturBiaya = StrukturBiaya::where('id_angkatan', $angkatan->id)->firstOrFail();
    expect($strukturBiaya->tahap)->toBe(1);
});

it('rejects a duplicate kombinasi kategori/prodi/angkatan/periode/komponen/tahap', function () {
    $admin = adminUser();
    $existing = StrukturBiaya::factory()->create(['tahap' => 1]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kategori_biaya', $existing->id_kategori_biaya)
        ->set('id_prodi', $existing->id_prodi)
        ->set('id_angkatan', $existing->id_angkatan)
        ->set('id_periode', $existing->id_periode)
        ->set('id_komponen_biaya', $existing->id_komponen_biaya)
        ->set('tahap', '1')
        ->set('nominal', '999')
        ->call('save')
        ->assertHasErrors('nominal');
});

it('defaults the periode filter to the active semester and can be cleared to show all periods', function () {
    $admin = adminUser();
    $periodeAktif = Semester::factory()->create(['is_active' => true]);
    $periodeLain = Semester::factory()->create(['is_active' => false]);

    // Nominal dibuat unik (bukan nama/kode) supaya tidak ikut cocok dengan teks <option> di
    // dropdown filter, yang selalu menampilkan semua kategori/prodi/semester terlepas dari
    // baris tabel yang sedang tersaring.
    StrukturBiaya::factory()->create(['id_periode' => $periodeAktif->id, 'nominal' => 1111111]);
    StrukturBiaya::factory()->create(['id_periode' => $periodeLain->id, 'nominal' => 2222222]);

    $component = Livewire::actingAs($admin)->test(Index::class);
    expect($component->get('filterPeriode'))->toBe((string) $periodeAktif->id);
    $component->assertSee('Rp1.111.111')->assertDontSee('Rp2.222.222');

    $component->set('filterPeriode', '');
    $component->assertSee('Rp1.111.111')->assertSee('Rp2.222.222');
});

it('admin dengan scope prodi hanya melihat struktur biaya milik prodinya', function () {
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Scope A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Scope B']);

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    StrukturBiaya::factory()->create(['id_prodi' => $prodiA->id]);
    $strukturB = StrukturBiaya::factory()->create(['id_prodi' => $prodiB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Prodi Scope A')
        ->assertDontSee('Prodi Scope B');

    expect(StrukturBiaya::find($strukturB->id))->not->toBeNull();
});

it('admin dengan scope prodi tidak bisa membuat struktur biaya di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodiB->id)
        ->set('id_angkatan', $angkatan->id)
        ->set('id_periode', $periode->id)
        ->set('nominal', '1000')
        ->call('save')
        ->assertStatus(403);

    expect(StrukturBiaya::where('id_prodi', $prodiB->id)->exists())->toBeFalse();
});

it('admin dengan scope prodi wajib memilih prodi saat membuat struktur biaya', function () {
    $prodiA = Prodi::factory()->create();

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_angkatan', $angkatan->id)
        ->set('id_periode', $periode->id)
        ->set('nominal', '1000')
        ->call('save')
        ->assertHasErrors('id_prodi');
});

it('admin dengan scope prodi tidak bisa menghapus struktur biaya di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $strukturB = StrukturBiaya::factory()->create(['id_prodi' => $prodiB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $strukturB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(StrukturBiaya::find($strukturB->id))->not->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.struktur-biaya'))->assertRedirect(route('login'));
});
