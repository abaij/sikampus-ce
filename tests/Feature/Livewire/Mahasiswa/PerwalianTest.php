<?php

use App\Livewire\Mahasiswa\Perwalian\Index as PerwalianIndex;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\DosenWaliBimbingan;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function perwalianMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.bimbingan-akademik'))->assertRedirect(route('login'));
});

it('shows a notice instead of a table when there is no active dosen wali', function () {
    [$user] = perwalianMahasiswaUser();

    $this->actingAs($user)->get(route('mahasiswa.bimbingan-akademik'))
        ->assertOk()
        ->assertSee('belum memiliki penugasan dosen wali aktif');
});

it('lists bimbingan rows for the active semester and dosen wali info', function () {
    [$user, $mahasiswa] = perwalianMahasiswaUser();
    $dosen = Dosen::factory()->create(['nama' => 'Dr. Wali Sejati', 'kode_dosen' => 'DW001']);
    $dosenWali = DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->active()->create();

    DosenWaliBimbingan::create([
        'id_dosen_wali' => $dosenWali->id,
        'id_semester' => $semester->id,
        'catatan_dosen' => 'Silakan fokus ke SKS semester ini.',
        'catatan_mhs' => null,
        'tanggal_bimbingan' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($user)->get(route('mahasiswa.bimbingan-akademik'))
        ->assertOk()
        ->assertSee('Dr. Wali Sejati')
        ->assertSee('DW001')
        ->assertSee('Silakan fokus ke SKS semester ini.');
});

it('lets a mahasiswa add a new bimbingan note for the active dosen wali', function () {
    [$user, $mahasiswa] = perwalianMahasiswaUser();
    $dosenWali = DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->active()->create();

    Livewire::actingAs($user)
        ->test(PerwalianIndex::class)
        ->call('openTambah')
        ->set('tambahSemester', $semester->id)
        ->set('tambahCatatan', 'Mohon jadwal bimbingan minggu depan.')
        ->call('submitTambah')
        ->assertHasNoErrors();

    $row = DosenWaliBimbingan::where('id_dosen_wali', $dosenWali->id)->firstOrFail();
    expect($row->catatan_mhs)->toBe('Mohon jadwal bimbingan minggu depan.');
    expect($row->catatan_dosen)->toBeNull();
    expect($row->waktu_validasi_mhs)->toBeNull();
});

it('rejects an empty catatan when adding a new bimbingan note', function () {
    [$user, $mahasiswa] = perwalianMahasiswaUser();
    DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->active()->create();

    Livewire::actingAs($user)
        ->test(PerwalianIndex::class)
        ->call('openTambah')
        ->set('tambahSemester', $semester->id)
        ->set('tambahCatatan', '   ')
        ->call('submitTambah')
        ->assertHasErrors('tambahCatatan');

    expect(DosenWaliBimbingan::count())->toBe(0);
});

it('lets a mahasiswa validate their own bimbingan note and locks it afterwards', function () {
    [$user, $mahasiswa] = perwalianMahasiswaUser();
    $dosenWali = DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->create();
    $row = DosenWaliBimbingan::create([
        'id_dosen_wali' => $dosenWali->id,
        'id_semester' => $semester->id,
        'catatan_dosen' => 'Catatan dari dosen.',
    ]);

    Livewire::actingAs($user)
        ->test(PerwalianIndex::class)
        ->call('openDetail', $row->id)
        ->set('detailCatatanDraft', 'Baik, siap.')
        ->set('detailValidasiChecked', true)
        ->call('saveDetail')
        ->assertHasNoErrors();

    $row->refresh();
    expect($row->catatan_mhs)->toBe('Baik, siap.');
    expect($row->waktu_validasi_mhs)->not->toBeNull();

    // Setelah divalidasi, mengubah catatan harus ditolak.
    Livewire::actingAs($user)
        ->test(PerwalianIndex::class)
        ->call('openDetail', $row->id)
        ->set('detailCatatanDraft', 'Coba ubah lagi.')
        ->call('saveDetail')
        ->assertHasErrors('detailCatatanDraft');

    expect($row->fresh()->catatan_mhs)->toBe('Baik, siap.');
});

it('does not let a mahasiswa view or edit bimbingan belonging to another dosen wali relationship', function () {
    [$user, $mahasiswa] = perwalianMahasiswaUser();
    DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    $otherDosenWali = DosenWali::factory()->create();
    $otherRow = DosenWaliBimbingan::create([
        'id_dosen_wali' => $otherDosenWali->id,
        'id_semester' => Semester::factory()->create()->id,
        'catatan_dosen' => 'Bukan milik mahasiswa ini.',
    ]);

    Livewire::actingAs($user)
        ->test(PerwalianIndex::class)
        ->call('openDetail', $otherRow->id)
        ->assertSet('detailRow', null)
        ->call('saveDetail')
        ->assertNotFound();
});

it('uploads an attachment when adding a new bimbingan note', function () {
    Storage::fake('public');

    [$user, $mahasiswa] = perwalianMahasiswaUser();
    DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->active()->create();

    Livewire::actingAs($user)
        ->test(PerwalianIndex::class)
        ->call('openTambah')
        ->set('tambahSemester', $semester->id)
        ->set('tambahCatatan', 'Ada lampiran proposal.')
        ->set('tambahFile', UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'))
        ->call('submitTambah')
        ->assertHasNoErrors();

    $row = DosenWaliBimbingan::firstOrFail();
    Storage::disk('public')->assertExists($row->file);
});
