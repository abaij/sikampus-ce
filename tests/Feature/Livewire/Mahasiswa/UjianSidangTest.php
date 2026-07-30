<?php

use App\Livewire\Mahasiswa\UjianSidang\Pengajuan;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use App\Models\UjianSidangPenguji;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function usMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.akhir-studi.ujian-sidang'))->assertRedirect(route('login'));
    $this->get(route('mahasiswa.akhir-studi.ujian-sidang.pengajuan'))->assertRedirect(route('login'));
});

it('shows an ineligibility notice when the mahasiswa has no tugas akhir at all', function () {
    [$user] = usMahasiswaUser();

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.ujian-sidang.pengajuan'))
        ->assertOk()
        ->assertSee('Anda belum memiliki data tugas akhir');
});

it('shows an ineligibility notice when tugas akhir exists but is not approved yet', function () {
    [$user, $mahasiswa] = usMahasiswaUser();
    TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Menunggu review',
        'status' => 'submitted',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.ujian-sidang.pengajuan'))
        ->assertOk()
        ->assertSee('judul tugas akhir Anda disetujui');
});

it('lists existing ujian sidang entries with penguji and status', function () {
    [$user, $mahasiswa] = usMahasiswaUser();
    $semester = Semester::factory()->create(['nama' => 'Ganjil 2025/2026']);
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    $us = UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => $semester->id,
        'tanggal_daftar' => now(),
        'status' => 'submitted',
    ]);
    $dosen = Dosen::factory()->create(['nama' => 'Dr. Penguji Utama']);
    UjianSidangPenguji::create(['id_ujian_sidang' => $us->id, 'id_dosen' => $dosen->id, 'is_ketua' => true, 'status' => 'draft']);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.ujian-sidang'))
        ->assertOk()
        ->assertSee('Sistem Rekomendasi')
        ->assertSee('Terkirim');

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.ujian-sidang.show', $us->id))
        ->assertOk()
        ->assertSee('Dr. Penguji Utama')
        ->assertSee('Ketua');
});

it('forbids viewing ujian sidang detail for another mahasiswa', function () {
    [$user] = usMahasiswaUser();
    $otherTa = TugasAkhir::create([
        'id_mahasiswa' => Mahasiswa::factory()->create()->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Punya orang lain',
        'status' => 'approved',
    ]);
    $otherUs = UjianSidang::create([
        'id_tugas_akhir' => $otherTa->id,
        'id_semester' => Semester::factory()->create()->id,
        'tanggal_daftar' => now(),
        'status' => 'submitted',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.ujian-sidang.show', $otherUs->id))->assertForbidden();
});

it('lets a mahasiswa submit a new ujian sidang pengajuan', function () {
    Storage::fake('public');

    [$user, $mahasiswa] = usMahasiswaUser();
    $semester = Semester::factory()->create();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->set('idTugasAkhir', (string) $ta->id)
        ->set('idSemester', (string) $semester->id)
        ->set('fileLaporan', UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf'))
        ->call('submit')
        ->assertRedirect(route('mahasiswa.akhir-studi.ujian-sidang'));

    $row = UjianSidang::where('id_tugas_akhir', $ta->id)->firstOrFail();
    expect($row->id_semester)->toBe($semester->id);
    expect($row->status)->toBe('submitted');
    Storage::disk('public')->assertExists($row->file_proposal);
});

it('rejects a duplicate pengajuan for the same tugas akhir and semester', function () {
    [$user, $mahasiswa] = usMahasiswaUser();
    $semester = Semester::factory()->create();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => $semester->id,
        'tanggal_daftar' => now(),
        'status' => 'submitted',
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->set('idTugasAkhir', (string) $ta->id)
        ->set('idSemester', (string) $semester->id)
        ->set('fileLaporan', UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf'))
        ->call('submit')
        ->assertHasErrors('idSemester');

    expect(UjianSidang::where('id_tugas_akhir', $ta->id)->count())->toBe(1);
});
