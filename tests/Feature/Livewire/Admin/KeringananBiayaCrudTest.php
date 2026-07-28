<?php

use App\Livewire\Admin\KeringananBiaya\Form;
use App\Livewire\Admin\KeringananBiaya\Index;
use App\Models\JenisKeringananBiaya;
use App\Models\KeringananBiaya;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari']);
    KeringananBiaya::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya'))->assertOk()->assertSee('Citra Lestari');
    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.create'))->assertOk()->assertSee('Tambah Keringanan Biaya');
});

it('creates a keringanan biaya via search-then-pick mahasiswa', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Dwi Kartika', 'nim' => '2023000700']);
    $jenis = JenisKeringananBiaya::factory()->create();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('mahasiswaSearch', 'Dwi Kartika')
        ->call('selectMahasiswa', $mahasiswa->id, 'Dwi Kartika (2023000700)')
        ->set('id_jenis_keringanan_biaya', $jenis->id)
        ->set('id_semester', $semester->id)
        ->set('nominal', '250000')
        ->set('status', 'pending')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.keringanan-biaya'));

    $row = KeringananBiaya::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect((float) $row->nominal)->toBe(250000.0);
    expect($row->status)->toBe('pending');
    expect($row->tanggal_approved)->toBeNull();
});

it('sets tanggal_approved and approved_by when status is approved', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $jenis = JenisKeringananBiaya::factory()->create();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('id_jenis_keringanan_biaya', $jenis->id)
        ->set('id_semester', $semester->id)
        ->set('nominal', '100000')
        ->set('status', 'approved')
        ->call('save');

    $row = KeringananBiaya::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($row->status)->toBe('approved');
    expect($row->tanggal_approved)->not->toBeNull();
    expect($row->approved_by)->not->toBeNull();
});

it('rejects a duplicate jenis+mahasiswa+semester combination', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $jenis = JenisKeringananBiaya::factory()->create();
    $semester = Semester::factory()->create();
    KeringananBiaya::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_jenis_keringanan_biaya' => $jenis->id,
        'id_semester' => $semester->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('id_jenis_keringanan_biaya', $jenis->id)
        ->set('id_semester', $semester->id)
        ->set('nominal', '100000')
        ->call('save')
        ->assertHasErrors('id_jenis_keringanan_biaya');

    expect(KeringananBiaya::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
});

it('updates an existing keringanan biaya without changing the mahasiswa', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $row = KeringananBiaya::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'nominal' => 100000]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $row->id])
        ->set('nominal', '200000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.keringanan-biaya'));

    expect((float) $row->fresh()->nominal)->toBe(200000.0);
    expect($row->fresh()->id_mahasiswa)->toBe($mahasiswa->id);
});

it('uploads a lampiran file and replaces the old one when re-uploaded', function () {
    Storage::fake('public');
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $jenis = JenisKeringananBiaya::factory()->create();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('id_jenis_keringanan_biaya', $jenis->id)
        ->set('id_semester', $semester->id)
        ->set('nominal', '100000')
        ->set('fileLampiranUpload', UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'))
        ->call('save');

    $row = KeringananBiaya::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($row->file_lampiran)->not->toBeNull();
    Storage::disk('public')->assertExists($row->file_lampiran);
    $oldPath = $row->file_lampiran;

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $row->id])
        ->set('fileLampiranUpload', UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf'))
        ->call('save');

    $row->refresh();
    Storage::disk('public')->assertExists($row->file_lampiran);
    Storage::disk('public')->assertMissing($oldPath);
    expect($row->file_lampiran)->not->toBe($oldPath);
});

it('deletes a keringanan biaya', function () {
    $admin = adminUser();
    $row = KeringananBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $row->id)
        ->call('delete');

    expect(KeringananBiaya::find($row->id))->toBeNull();
});

it('filters the index by status', function () {
    $admin = adminUser();
    $mahasiswaPending = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Pending']);
    $mahasiswaApproved = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Approved']);
    KeringananBiaya::factory()->create(['id_mahasiswa' => $mahasiswaPending->id, 'status' => 'pending']);
    KeringananBiaya::factory()->create(['id_mahasiswa' => $mahasiswaApproved->id, 'status' => 'approved']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterStatus', 'approved')
        ->assertSee('Mahasiswa Approved')
        ->assertDontSee('Mahasiswa Pending');
});

it('filters the index by prodi scope for a scoped admin_keuangan', function () {
    $admin = adminUser('admin_keuangan');
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Keringanan A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Keringanan B']);
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa Keringanan A']);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa Keringanan B']);
    KeringananBiaya::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    KeringananBiaya::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Mahasiswa Keringanan A')
        ->assertDontSee('Mahasiswa Keringanan B');
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.keringanan-biaya'))->assertRedirect(route('login'));
});
