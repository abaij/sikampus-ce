<?php

use App\Livewire\Prodi\JadwalKuliah\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\Semester;
use Livewire\Livewire;

it('lists only jadwal within the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Matkul Prodi A']);
    $matkulB = Matkul::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Matkul Prodi B']);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id, 'nama_matkul' => $matkulA->nama]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id, 'nama_matkul' => $matkulB->nama]);
    $kelasA = Kelas::factory()->create(['id_prodi' => $prodiA->id, 'id_kurikulum_matkul' => $kmA->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id, 'id_kurikulum_matkul' => $kmB->id]);
    Jadwal::factory()->create(['id_kelas' => $kelasA->id]);
    Jadwal::factory()->create(['id_kelas' => $kelasB->id]);

    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Matkul Prodi A')
        ->assertDontSee('Matkul Prodi B');
});

it('shows hari, jam, ruangan, and dosen names for a jadwal row', function () {
    $prodi = Prodi::factory()->create();
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Pemrograman Web']);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'nama_matkul' => $matkul->nama]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id]);
    $ruangan = Ruangan::factory()->create(['nama' => 'Lab Komputer 1']);
    $jadwal = Jadwal::factory()->create([
        'id_kelas' => $kelas->id,
        'hari' => 'senin',
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'id_ruangan' => $ruangan->id,
    ]);
    $dosen = Dosen::factory()->create(['nama' => 'Dr. Budi Santoso']);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Pemrograman Web')
        ->assertSee('Senin')
        ->assertSee('08:00')
        ->assertSee('10:00')
        ->assertSee('Lab Komputer 1')
        ->assertSee('Dr. Budi Santoso');
});

it('filters by semester, kelas, hari, and search', function () {
    $prodi = Prodi::factory()->create();
    $semesterA = Semester::factory()->create(['kode' => '20241']);
    $semesterB = Semester::factory()->create(['kode' => '20242']);
    $matkulA = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Aljabar Linear']);
    $matkulB = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Basis Data']);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id, 'nama_matkul' => $matkulA->nama]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id, 'nama_matkul' => $matkulB->nama]);
    $kelasA = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmA->id, 'id_semester' => $semesterA->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmB->id, 'id_semester' => $semesterB->id]);
    $jadwalA = Jadwal::factory()->create(['id_kelas' => $kelasA->id, 'hari' => 'senin']);
    $jadwalB = Jadwal::factory()->create(['id_kelas' => $kelasB->id, 'hari' => 'selasa']);
    $kaprodi = kaprodiUser($prodi);

    // wire:key dipakai (bukan nama matkul) supaya assertDontSee tidak salah gagal gara-gara nama
    // matkul yang sama juga muncul sebagai label opsi di dropdown filter Kelas (yang tidak ikut
    // tersaring oleh filter selain semester).
    $component = Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->set('filterSemester', (string) $semesterA->id)
        ->assertSee('jadwal-'.$jadwalA->id)
        ->assertDontSee('jadwal-'.$jadwalB->id);

    $component
        ->set('filterSemester', '')
        ->set('filterKelas', (string) $kelasB->id)
        ->assertSee('jadwal-'.$jadwalB->id)
        ->assertDontSee('jadwal-'.$jadwalA->id);

    $component
        ->set('filterKelas', '')
        ->set('filterHari', 'senin')
        ->assertSee('jadwal-'.$jadwalA->id)
        ->assertDontSee('jadwal-'.$jadwalB->id);

    $component
        ->set('filterHari', '')
        ->set('search', 'Basis')
        ->assertSee('jadwal-'.$jadwalB->id)
        ->assertDontSee('jadwal-'.$jadwalA->id);
});

it('has no aksi column or write actions (read-only portal)', function () {
    $prodi = Prodi::factory()->create();
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id]);
    Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.jadwal-kuliah'))->getContent();

    expect($html)->not->toContain('wire:click="confirmDelete');
    expect($html)->not->toContain('Tambah Jadwal');

    $component = Livewire::actingAs($kaprodi)->test(Index::class);
    expect(method_exists($component->instance(), 'delete'))->toBeFalse();
});

it('does not default to the active semester, so kelas options and jadwal show even when the active semester has no data for this prodi', function () {
    $prodi = Prodi::factory()->create();
    $semesterAktif = Semester::factory()->create(['kode' => '20241', 'is_active' => true]);
    $semesterLain = Semester::factory()->create(['kode' => '20232', 'is_active' => false]);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Struktur Data']);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'nama_matkul' => $matkul->nama]);
    // Kelas & jadwal hanya ada di semester yang BUKAN aktif — kasus yang sebelumnya membuat
    // filter Kelas kosong dan daftar jadwal kosong begitu halaman dibuka, karena filterSemester
    // otomatis terisi id semester aktif yang tidak punya data untuk prodi ini.
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id, 'id_semester' => $semesterLain->id]);
    Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSet('filterSemester', '')
        ->assertSee('Struktur Data');
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.jadwal-kuliah'))->assertRedirect(route('login'));
});
