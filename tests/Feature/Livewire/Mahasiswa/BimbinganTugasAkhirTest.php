<?php

use App\Livewire\Mahasiswa\BimbinganTugasAkhir\Show as BimbinganShow;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\TugasAkhirBimbingan;
use App\Models\TugasAkhirPembimbing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function btaMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.akhir-studi.bimbingan-tugas-akhir'))->assertRedirect(route('login'));
});

it('shows a notice when the mahasiswa has no tugas akhir at all', function () {
    [$user] = btaMahasiswaUser();

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.bimbingan-tugas-akhir'))
        ->assertOk()
        ->assertSee('Belum ada data tugas akhir');
});

it('shows a notice when tugas akhir exists but none is approved yet', function () {
    [$user, $mahasiswa] = btaMahasiswaUser();
    TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Menunggu review',
        'status' => 'submitted',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.bimbingan-tugas-akhir'))
        ->assertOk()
        ->assertSee('Belum ada pengajuan tugas akhir yang berstatus disetujui');
});

it('lists approved tugas akhir with bimbingan count', function () {
    [$user, $mahasiswa] = btaMahasiswaUser();
    $semester = Semester::factory()->create(['nama' => 'Ganjil 2025/2026']);
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    $dosen = Dosen::factory()->create();
    TugasAkhirBimbingan::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'tanggal_bimbingan' => now()]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.bimbingan-tugas-akhir'))
        ->assertOk()
        ->assertSee('Sistem Rekomendasi')
        ->assertSee('Ganjil 2025/2026')
        ->assertSee('1');
});

it('forbids viewing bimbingan detail for a tugas akhir that is not approved', function () {
    [$user, $mahasiswa] = btaMahasiswaUser();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Belum disetujui',
        'status' => 'submitted',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.bimbingan-tugas-akhir.show', $ta->id))->assertForbidden();
});

it('forbids viewing bimbingan detail for another mahasiswa\'s tugas akhir', function () {
    [$user] = btaMahasiswaUser();
    $otherTa = TugasAkhir::create([
        'id_mahasiswa' => Mahasiswa::factory()->create()->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Punya orang lain',
        'status' => 'approved',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.bimbingan-tugas-akhir.show', $otherTa->id))->assertNotFound();
});

it('lets a mahasiswa add a bimbingan entry for an assigned pembimbing', function () {
    Storage::fake('public');

    [$user, $mahasiswa] = btaMahasiswaUser();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    $dosen = Dosen::factory()->create();
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    Livewire::actingAs($user)
        ->test(BimbinganShow::class, ['id' => $ta->id])
        ->call('openAddModal')
        ->set('addTanggal', '2026-01-10')
        ->set('addIdDosen', (string) $dosen->id)
        ->set('addCatatan', 'Diskusi bab 1')
        ->set('addFile', UploadedFile::fake()->create('catatan.pdf', 50, 'application/pdf'))
        ->call('submitAdd')
        ->assertHasNoErrors();

    $row = TugasAkhirBimbingan::where('id_tugas_akhir', $ta->id)->firstOrFail();
    expect($row->catatan_mahasiswa)->toBe('Diskusi bab 1');
    expect($row->created_by)->toBe($mahasiswa->nama);
    Storage::disk('public')->assertExists($row->file);
});

it('rejects a dosen who is not an assigned pembimbing when adding a bimbingan entry', function () {
    [$user, $mahasiswa] = btaMahasiswaUser();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    $dosenLain = Dosen::factory()->create();

    Livewire::actingAs($user)
        ->test(BimbinganShow::class, ['id' => $ta->id])
        ->set('addTanggal', '2026-01-10')
        ->set('addIdDosen', (string) $dosenLain->id)
        ->call('submitAdd')
        ->assertHasErrors('addIdDosen');

    expect(TugasAkhirBimbingan::count())->toBe(0);
});

it('rejects a duplicate bimbingan entry for the same date and dosen', function () {
    [$user, $mahasiswa] = btaMahasiswaUser();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    $dosen = Dosen::factory()->create();
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);
    TugasAkhirBimbingan::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'tanggal_bimbingan' => '2026-01-10']);

    Livewire::actingAs($user)
        ->test(BimbinganShow::class, ['id' => $ta->id])
        ->set('addTanggal', '2026-01-10')
        ->set('addIdDosen', (string) $dosen->id)
        ->call('submitAdd')
        ->assertHasErrors('addTanggal');

    expect(TugasAkhirBimbingan::where('id_tugas_akhir', $ta->id)->count())->toBe(1);
});

it('lets a mahasiswa update their own catatan on a bimbingan entry but never touches catatan dosen', function () {
    [$user, $mahasiswa] = btaMahasiswaUser();
    $ta = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => Semester::factory()->create()->id,
        'judul' => 'Sistem Rekomendasi',
        'status' => 'approved',
    ]);
    $dosen = Dosen::factory()->create();
    $row = TugasAkhirBimbingan::create([
        'id_tugas_akhir' => $ta->id,
        'id_dosen' => $dosen->id,
        'tanggal_bimbingan' => now(),
        'catatan_dosen' => 'Arahan dari dosen.',
    ]);

    Livewire::actingAs($user)
        ->test(BimbinganShow::class, ['id' => $ta->id])
        ->call('openDetailModal', $row->id)
        ->set('detailCatatanDraft', 'Sudah saya kerjakan.')
        ->call('saveDetail')
        ->assertHasNoErrors();

    $row->refresh();
    expect($row->catatan_mahasiswa)->toBe('Sudah saya kerjakan.');
    expect($row->catatan_dosen)->toBe('Arahan dari dosen.');
});
