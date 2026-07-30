<?php

use App\Livewire\Mahasiswa\TugasAkhir\Index as TugasAkhirIndex;
use App\Livewire\Mahasiswa\TugasAkhir\Pengajuan;
use App\Models\JenisMatkul;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function taMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

function buatKrsTaDisetujui(Mahasiswa $mahasiswa, Semester $semester): Krs
{
    $jenisTa = JenisMatkul::firstOrCreate(['kode' => 'TA'], ['nama' => 'Tugas Akhir']);
    $matkul = Matkul::factory()->create(['id_jenis_matkul' => $jenisTa->id, 'kode' => 'TA401', 'nama' => 'Skripsi']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id, 'id_semester' => $semester->id]);

    return Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.akhir-studi.tugas-akhir'))->assertRedirect(route('login'));
    $this->get(route('mahasiswa.akhir-studi.tugas-akhir.pengajuan'))->assertRedirect(route('login'));
});

it('shows an ineligibility notice when the mahasiswa has not contracted an approved TA course', function () {
    [$user] = taMahasiswaUser();
    Semester::factory()->active()->create();
    JenisMatkul::firstOrCreate(['kode' => 'TA'], ['nama' => 'Tugas Akhir']);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.tugas-akhir'))
        ->assertOk()
        ->assertSee('Belum memenuhi syarat pengajuan baru')
        ->assertSee('mengontrak mata kuliah dengan jenis Tugas Akhir');
});

it('shows eligibility and lists existing tugas akhir rows filtered by status and semester', function () {
    [$user, $mahasiswa] = taMahasiswaUser();
    $semester = Semester::factory()->active()->create();
    buatKrsTaDisetujui($mahasiswa, $semester);

    TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Sistem Informasi Akademik',
        'status' => 'submitted',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.tugas-akhir'))
        ->assertOk()
        ->assertSee('TA401')
        ->assertSee('Skripsi')
        ->assertSee('Sistem Informasi Akademik')
        ->assertSee('Terkirim');

    Livewire::actingAs($user)
        ->test(TugasAkhirIndex::class)
        ->set('filterStatus', 'approved')
        ->assertDontSee('Sistem Informasi Akademik')
        ->assertSee('Belum ada data yang cocok dengan filter');
});

it('lets an eligible mahasiswa submit a new tugas akhir proposal', function () {
    Storage::fake('public');

    [$user, $mahasiswa] = taMahasiswaUser();
    $semester = Semester::factory()->active()->create();
    buatKrsTaDisetujui($mahasiswa, $semester);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->set('judul', 'Rancang Bangun Sistem Informasi')
        ->set('topik', 'Rekayasa perangkat lunak')
        ->set('file', UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertRedirect(route('mahasiswa.akhir-studi.tugas-akhir'));

    $row = TugasAkhir::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($row->judul)->toBe('Rancang Bangun Sistem Informasi');
    expect($row->status)->toBe('submitted');
    Storage::disk('public')->assertExists($row->file);
});

it('does not show the pengajuan form once a submission already exists and is not editable', function () {
    [$user, $mahasiswa] = taMahasiswaUser();
    $semester = Semester::factory()->active()->create();
    buatKrsTaDisetujui($mahasiswa, $semester);
    TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Judul lama',
        'status' => 'submitted',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.tugas-akhir.pengajuan'))
        ->assertOk()
        ->assertSee('tidak dalam mode perbaikan')
        ->assertDontSee('Kirim pengajuan');
});

it('lets a mahasiswa resubmit a rejected tugas akhir', function () {
    [$user, $mahasiswa] = taMahasiswaUser();
    $semester = Semester::factory()->active()->create();
    buatKrsTaDisetujui($mahasiswa, $semester);
    $row = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Judul lama',
        'status' => 'rejected',
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->assertSet('judul', 'Judul lama')
        ->set('judul', 'Judul revisi')
        ->call('submit')
        ->assertRedirect(route('mahasiswa.akhir-studi.tugas-akhir'));

    $row->refresh();
    expect($row->judul)->toBe('Judul revisi');
    expect($row->status)->toBe('submitted');
});

it('shows the detail page to the owning mahasiswa and forbids other mahasiswa', function () {
    [$user, $mahasiswa] = taMahasiswaUser();
    $semester = Semester::factory()->create();
    $row = TugasAkhir::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Aplikasi Mobile Akademik',
        'status' => 'draft',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.tugas-akhir.show', $row->id))
        ->assertOk()
        ->assertSee('Aplikasi Mobile Akademik')
        ->assertSee('Ubah pengajuan');

    [$otherUser] = taMahasiswaUser();
    $this->actingAs($otherUser)->get(route('mahasiswa.akhir-studi.tugas-akhir.show', $row->id))
        ->assertForbidden();
});
