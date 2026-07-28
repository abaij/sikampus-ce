<?php

use App\Livewire\Admin\Tagihan\Form;
use App\Livewire\Admin\Tagihan\Index;
use App\Livewire\Admin\Tagihan\Show;
use App\Models\KategoriBiaya;
use App\Models\KategoriBiayaMahasiswa;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Models\TagihanRinci;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari']);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))->assertOk()->assertSee('Citra Lestari');
    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.create'))->assertOk()->assertSee('Tambah Tagihan');
});

it('creates a tagihan with rincian and computes the total automatically', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $komponenA = KomponenBiaya::factory()->create();
    $komponenB = KomponenBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' — '.$mahasiswa->nama)
        ->set('id_semester', $semester->id)
        ->set('rincian.0.id_komponen_biaya', (string) $komponenA->id)
        ->set('rincian.0.nominal', '2000000')
        ->call('addRincian')
        ->set('rincian.1.id_komponen_biaya', (string) $komponenB->id)
        ->set('rincian.1.nominal', '500000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.tagihan'));

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect((float) $tagihan->total)->toBe(2500000.0);
    expect($tagihan->no_tagihan)->toStartWith('INV-'.now()->format('Ymd').'-');
    expect(TagihanRinci::where('id_tagihan', $tagihan->id)->count())->toBe(2);
});

it('updates a tagihan by replacing its rincian', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    $komponenLama = KomponenBiaya::factory()->create();
    TagihanRinci::factory()->create(['id_tagihan' => $tagihan->id, 'id_komponen_biaya' => $komponenLama->id, 'nominal' => 1000000]);
    $komponenBaru = KomponenBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $tagihan->id])
        ->set('rincian.0.id_komponen_biaya', (string) $komponenBaru->id)
        ->set('rincian.0.nominal', '3000000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.tagihan'));

    expect((float) $tagihan->fresh()->total)->toBe(3000000.0);
    $rincian = TagihanRinci::where('id_tagihan', $tagihan->id)->get();
    expect($rincian)->toHaveCount(1);
    expect($rincian->first()->id_komponen_biaya)->toBe($komponenBaru->id);
});

it('deletes a tagihan', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $tagihan->id)
        ->call('delete');

    expect(Tagihan::find($tagihan->id))->toBeNull();
});

it('rejects a duplicate mahasiswa/semester combination', function () {
    $admin = adminUser();
    $existing = Tagihan::factory()->create();
    $komponen = KomponenBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $existing->id_mahasiswa, 'label')
        ->set('id_semester', $existing->id_semester)
        ->set('rincian.0.id_komponen_biaya', (string) $komponen->id)
        ->set('rincian.0.nominal', '1000')
        ->call('save')
        ->assertHasErrors('id_semester');
});

it('rejects duplicate komponen biaya within rincian', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $komponen = KomponenBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('id_semester', $semester->id)
        ->set('rincian.0.id_komponen_biaya', (string) $komponen->id)
        ->set('rincian.0.nominal', '1000')
        ->call('addRincian')
        ->set('rincian.1.id_komponen_biaya', (string) $komponen->id)
        ->set('rincian.1.nominal', '2000')
        ->call('save')
        ->assertHasErrors('rincian');
});

it('auto-fills nominal from struktur biaya when a komponen biaya is chosen', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create();
    $kategoriBiaya = KategoriBiaya::factory()->create();
    $komponen = KomponenBiaya::factory()->create();

    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'id_semester_masuk' => $angkatan->id]);
    KategoriBiayaMahasiswa::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kategori_biaya' => $kategoriBiaya->id,
        'status' => 'active',
    ]);

    StrukturBiaya::factory()->create([
        'id_kategori_biaya' => $kategoriBiaya->id,
        'id_prodi' => $prodi->id,
        'id_angkatan' => $angkatan->id,
        'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id,
        'tahap' => 1,
        'nominal' => 4500000,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('rincian.0.id_komponen_biaya', (string) $komponen->id)
        ->assertSet('rincian.0.nominal', '4500000.00');
});

it('does not overwrite a nominal the user already typed when auto-filling', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $komponen = KomponenBiaya::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    StrukturBiaya::factory()->create([
        'id_kategori_biaya' => null,
        'id_prodi' => null,
        'id_angkatan' => $angkatan->id,
        'id_komponen_biaya' => $komponen->id,
        'tahap' => 1,
        'nominal' => 9999999,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('rincian.0.nominal', '1234')
        ->set('rincian.0.id_komponen_biaya', (string) $komponen->id)
        ->assertSet('rincian.0.nominal', '1234');
});

it('renders the tagihan show page with rincian and approved pembayaran', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    TagihanRinci::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 1000000]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 400000, 'approved_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.show', $tagihan->id))
        ->assertOk()
        ->assertSee($tagihan->no_tagihan)
        ->assertSee('Rp400.000');

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $tagihan->id])
        ->assertSet('totalPembayaranDisetujui', 400000.0)
        ->assertSet('sisaPembayaranDisetujui', 600000.0)
        ->assertSet('statusPembayaranAcc', 'dibayar_sebagian');
});

it('admin dengan scope prodi tidak bisa membuka detail tagihan di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $tagihanB = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.show', $tagihanB->id))
        ->assertStatus(403);
});

it('carries the current page/filter state from index into the Lihat link', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    Mahasiswa::factory()->count(15)->create(['id_prodi' => $prodi->id])->each(
        fn (Mahasiswa $m) => Tagihan::factory()->create(['id_mahasiswa' => $m->id])
    );

    $expectedQuery = 'id_prodi='.$prodi->id.'&page=2';

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodi->id)
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee($expectedQuery);
});

it('carries the current page/filter state from index into the Ubah link', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    Tagihan::factory()->count(12)->sequence(fn ($seq) => ['tanggal_tagihan' => now()->subDays($seq->index)])->create(['id_mahasiswa' => $mahasiswa->id]);
    $pageTwoTagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->orderByDesc('tanggal_tagihan')->skip(10)->first();

    $query = 'id_prodi='.$prodi->id.'&page=2';

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan').'?'.$query)
        ->assertOk()
        ->assertSee(route('admin.keuangan.tagihan.show', $pageTwoTagihan->id).'?'.$query)
        ->assertSee(route('admin.keuangan.tagihan.edit', $pageTwoTagihan->id).'?'.$query);
});

it('points the Kembali button on the show page to the page/search state carried in the query string', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.show', $tagihan->id).'?page=2&search=budi&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.keuangan.tagihan').'?page=2&search=budi')
        ->assertDontSee('unexpected=1');
});

it('carries the forwarded state into the Ubah link on the show page too', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.show', $tagihan->id).'?page=2&search=budi')
        ->assertOk()
        ->assertSee(route('admin.keuangan.tagihan.edit', $tagihan->id).'?page=2&search=budi');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create();
    TagihanRinci::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => $tagihan->total]);

    $expectedBackUrl = route('admin.keuangan.tagihan').'?page=2&search=budi';

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.edit', $tagihan->id).'?page=2&search=budi&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'budi'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $tagihan->id])
        ->set('keterangan', 'diubah')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('falls back to the plain index url when no state was carried over', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.show', $tagihan->id))
        ->assertOk()
        ->assertSee(route('admin.keuangan.tagihan'));
});

it('admin dengan scope prodi hanya melihat tagihan mahasiswa di prodinya', function () {
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Scope A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Scope B']);

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa A']);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa B']);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Mahasiswa A')
        ->assertDontSee('Mahasiswa B');
});

it('admin dengan scope prodi tidak bisa menghapus tagihan di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $tagihanB = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $tagihanB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Tagihan::find($tagihanB->id))->not->toBeNull();
});

it('admin dengan scope prodi tidak bisa membuka edit tagihan di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $tagihanB = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.edit', $tagihanB->id))
        ->assertStatus(403);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.tagihan'))->assertRedirect(route('login'));
});
