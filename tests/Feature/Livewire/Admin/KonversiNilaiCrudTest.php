<?php

use App\Livewire\Admin\KonversiNilai\Form;
use App\Livewire\Admin\KonversiNilai\Index;
use App\Models\JenisKonversiNilai;
use App\Models\KonversiNilai;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Prodi;
use Livewire\Livewire;

it('renders index and shows the aggregated row for a mahasiswa with konversi nilai', function () {
    $admin = adminUser();

    $prodi = Prodi::factory()->create(['nama' => 'Prodi Uji']);
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Budi Santoso', 'nim' => '2024000001', 'id_prodi' => $prodi->id]);
    KonversiNilai::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'sks_lama' => 3, 'sks_baru' => 4]);

    $this->actingAs($admin)
        ->get(route('admin.akademik.konversi-nilai'))
        ->assertOk()
        ->assertSee('2024000001')
        ->assertSee('Budi Santoso');
});

it('renders the create form as a full page', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.akademik.konversi-nilai.create'))->assertOk()->assertSee('Tambah Konversi Nilai');
});

it('renders the rincian page for a mahasiswa', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    KonversiNilai::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'nama_mk_lama' => 'Kalkulus Dasar Lama',
        'nama_mk_baru' => 'Kalkulus Dasar Baru',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.akademik.konversi-nilai.show', $mahasiswa->id))
        ->assertOk()
        ->assertSee('Kalkulus Dasar Lama')
        ->assertSee('Kalkulus Dasar Baru');
});

it('creates multiple konversi nilai rows in one bulk submit', function () {
    $admin = adminUser();

    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'status' => 'active']);
    $jenis = JenisKonversiNilai::factory()->create();
    $matkulA = Matkul::factory()->create(['kode' => 'MKA', 'nama' => 'Mata Kuliah A', 'sks' => 3]);
    $matkulB = Matkul::factory()->create(['kode' => 'MKB', 'nama' => 'Mata Kuliah B', 'sks' => 2]);
    $kmA = KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id, 'id_matkul' => $matkulA->id, 'sks' => 3]);
    $kmB = KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id, 'id_matkul' => $matkulB->id, 'sks' => 2]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswaOption', $mahasiswa->id)
        ->set('kurikulumId', $kurikulum->id)
        ->set('idJenisKonversi', $jenis->id)
        ->set('rows.0.kode_mk_lama', 'OLD-A')
        ->set('rows.0.nama_mk_lama', 'Lama A')
        ->set('rows.0.sks_lama', '3')
        ->set('rows.0.nilai_lama', 'B')
        ->set('rows.0.id_kurikulum_matkul', $kmA->id)
        ->set('rows.0.nilai_baru', 'A')
        ->call('addRow')
        ->set('rows.1.kode_mk_lama', 'OLD-B')
        ->set('rows.1.nama_mk_lama', 'Lama B')
        ->set('rows.1.sks_lama', '2')
        ->set('rows.1.nilai_lama', 'C')
        ->set('rows.1.id_kurikulum_matkul', $kmB->id)
        ->set('rows.1.nilai_baru', 'B')
        ->call('save')
        ->assertRedirect(route('admin.akademik.konversi-nilai'));

    expect(KonversiNilai::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(2);

    $rowA = KonversiNilai::where('id_mahasiswa', $mahasiswa->id)->where('kode_mk_lama', 'OLD-A')->firstOrFail();
    expect($rowA->kode_mk_baru)->toBe('MKA');
    expect($rowA->nama_mk_baru)->toBe('Mata Kuliah A');
    expect($rowA->sks_baru)->toBe(3);
    expect($rowA->nilai_baru)->toBe('A');
    expect($rowA->is_approved)->toBeTrue();
});

it('rejects a bulk submit with a duplicate kode_mk_lama+kode_mk_baru combination for the same mahasiswa', function () {
    $admin = adminUser();

    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'status' => 'active']);
    $jenis = JenisKonversiNilai::factory()->create();
    $matkul = Matkul::factory()->create(['kode' => 'MKA', 'nama' => 'Mata Kuliah A', 'sks' => 3]);
    $km = KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id, 'id_matkul' => $matkul->id, 'sks' => 3]);

    KonversiNilai::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'kode_mk_lama' => 'OLD-A',
        'kode_mk_baru' => 'MKA',
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswaOption', $mahasiswa->id)
        ->set('kurikulumId', $kurikulum->id)
        ->set('idJenisKonversi', $jenis->id)
        ->set('rows.0.kode_mk_lama', 'OLD-A')
        ->set('rows.0.nama_mk_lama', 'Lama A')
        ->set('rows.0.sks_lama', '3')
        ->set('rows.0.nilai_lama', 'B')
        ->set('rows.0.id_kurikulum_matkul', $km->id)
        ->set('rows.0.nilai_baru', 'A')
        ->call('save');

    expect(KonversiNilai::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.akademik.konversi-nilai'))->assertRedirect(route('login'));
});

it('admin dengan scope prodi hanya melihat konversi nilai mahasiswa di prodinya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $mahasiswaA = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi A', 'nim' => '2024000011', 'id_prodi' => $prodiA->id]);
    $mahasiswaB = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi B', 'nim' => '2024000022', 'id_prodi' => $prodiB->id]);
    KonversiNilai::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    KonversiNilai::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Mahasiswa Prodi A')
        ->assertDontSee('Mahasiswa Prodi B');

    $this->actingAs($admin)
        ->get(route('admin.akademik.konversi-nilai.show', $mahasiswaB->id))
        ->assertForbidden();
});
