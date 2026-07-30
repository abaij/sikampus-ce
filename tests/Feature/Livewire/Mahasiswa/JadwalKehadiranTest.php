<?php

use App\Livewire\Mahasiswa\Jadwal\Detail as JadwalDetail;
use App\Livewire\Mahasiswa\Kehadiran\Index as KehadiranIndex;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JenisKuliah;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Perkuliahan;
use App\Models\Ruangan;
use App\Models\Semester;
use App\Models\Tugas;
use App\Models\TugasMahasiswa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function mahasiswaUserWithRecord(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

function buatKelasKontrak(Mahasiswa $mahasiswa, Semester $semester): Kelas
{
    $matkul = Matkul::factory()->create(['nama' => 'Struktur Data', 'kode' => 'IF201', 'sks' => 3]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
    ]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    return $kelas;
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.jadwal'))->assertRedirect(route('login'));
    $this->get(route('mahasiswa.kehadiran'))->assertRedirect(route('login'));
});

it('lists jadwal for the kelas the mahasiswa contracted this active semester', function () {
    [$user, $mahasiswa] = mahasiswaUserWithRecord();
    $semester = Semester::factory()->active()->create();
    $kelas = buatKelasKontrak($mahasiswa, $semester);
    $ruangan = Ruangan::factory()->create(['nama' => 'Lab 1']);
    $jenisKuliah = JenisKuliah::factory()->create(['nama' => 'Tatap Muka']);

    $jadwal = Jadwal::factory()->create([
        'id_kelas' => $kelas->id,
        'hari' => 'senin',
        'id_ruangan' => $ruangan->id,
        'id_jenis_kuliah' => $jenisKuliah->id,
    ]);

    $this->actingAs($user)->get(route('mahasiswa.jadwal'))
        ->assertOk()
        ->assertSee('IF201')
        ->assertSee('Struktur Data')
        ->assertSee('Lab 1')
        ->assertSee(route('mahasiswa.jadwal.detail', $jadwal->id), false);
});

it('shows jadwal detail with perkuliahan, materi, and kehadiran for the logged-in mahasiswa only', function () {
    [$user, $mahasiswa] = mahasiswaUserWithRecord();
    $semester = Semester::factory()->active()->create();
    $kelas = buatKelasKontrak($mahasiswa, $semester);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $perkuliahan = Perkuliahan::factory()->create([
        'id_jadwal' => $jadwal->id,
        'materi' => 'Pengantar Array',
    ]);
    Kehadiran::factory()->create([
        'id_perkuliahan' => $perkuliahan->id,
        'id_mhs' => $mahasiswa->id,
        'status' => 'hadir',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.jadwal.detail', $jadwal->id))
        ->assertOk()
        ->assertSee('Pengantar Array')
        ->assertSee('Hadir');
});

it('forbids viewing jadwal detail for a kelas the mahasiswa never contracted', function () {
    [$user] = mahasiswaUserWithRecord();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $this->actingAs($user)->get(route('mahasiswa.jadwal.detail', $jadwal->id))->assertForbidden();
});

it('lets a mahasiswa submit a tugas for a jadwal they contracted', function () {
    Storage::fake('public');

    [$user, $mahasiswa] = mahasiswaUserWithRecord();
    $semester = Semester::factory()->active()->create();
    $kelas = buatKelasKontrak($mahasiswa, $semester);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $tugas = Tugas::create([
        'id_jadwal' => $jadwal->id,
        'id_dosen' => Dosen::factory()->create()->id,
        'nama' => 'Tugas 1',
        'deskripsi' => 'Kerjakan soal halaman 10',
    ]);

    Livewire::actingAs($user)
        ->test(JadwalDetail::class, ['id' => $jadwal->id])
        ->call('setTab', 'tugas')
        ->call('startSubmit', $tugas->id)
        ->set('tugasFile', UploadedFile::fake()->create('jawaban.pdf', 100, 'application/pdf'))
        ->set('tugasKeterangan', 'Sudah selesai')
        ->call('submitTugas');

    $submission = TugasMahasiswa::where('id_tugas', $tugas->id)->where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($submission->status)->toBe('submitted');
    expect($submission->keterangan)->toBe('Sudah selesai');
    Storage::disk('public')->assertExists($submission->file);
});

it('shows kehadiran rekap ringkasan and per-pertemuan status for the selected kelas', function () {
    [$user, $mahasiswa] = mahasiswaUserWithRecord();
    $semester = Semester::factory()->active()->create();
    $kelas = buatKelasKontrak($mahasiswa, $semester);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $p1 = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeek()]);
    $p2 = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()]);
    Kehadiran::factory()->create(['id_perkuliahan' => $p1->id, 'id_mhs' => $mahasiswa->id, 'status' => 'hadir']);
    Kehadiran::factory()->create(['id_perkuliahan' => $p2->id, 'id_mhs' => $mahasiswa->id, 'status' => 'izin']);

    Livewire::actingAs($user)
        ->test(KehadiranIndex::class)
        ->assertSet('filterKelas', (string) $kelas->id)
        ->assertSee('IF201')
        ->assertSeeInOrder(['Ke-1', 'Ke-2'])
        ->assertSee('Hadir')
        ->assertSee('Izin');
});

it('forbids viewing kehadiran rekap for a kelas the mahasiswa never contracted', function () {
    [$user] = mahasiswaUserWithRecord();
    $kelas = Kelas::factory()->create();

    Livewire::actingAs($user)
        ->test(KehadiranIndex::class)
        ->set('filterKelas', (string) $kelas->id)
        ->assertForbidden();
});
