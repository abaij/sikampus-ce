<?php

use App\Livewire\Admin\Kelas\Import;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Susun file xlsx sungguhan (bukan UploadedFile::fake() biasa — isinya harus bisa
 * diparse PhpSpreadsheet) dengan urutan kolom persis KelasController::import.
 * Dibungkus lewat UploadedFile::fake()->createWithContent supaya hasilnya instance
 * Illuminate\Http\Testing\File — Livewire test harness butuh properti publik ->name.
 */
function makeKelasImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data');
    $sheet->fromArray(['header'], null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'kelas_import_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path));
}

it('renders the import page with a link to download the template', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.import'))
        ->assertOk()
        ->assertSee('Proses Import')
        ->assertSee(route('admin.akademik.kelas.template'));
});

it('shows download template and import links on the kelas index page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas'))
        ->assertOk()
        ->assertSee(route('admin.akademik.kelas.template'))
        ->assertSee(route('admin.akademik.kelas.import'));
});

it('downloads a template with an xlsx content type', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.template'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('imports a kelas row using the new 13-column format and reports the success count', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['kode' => 'TI']);
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'KUR-2024']);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'MK001']);
    KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id, 'id_matkul' => $matkul->id]);
    $semester = Semester::factory()->create(['kode' => '20241']);
    $dosen = Dosen::factory()->create(['kode_dosen' => 'DSN001']);

    $file = makeKelasImportFile([
        [$prodi->kode, $kurikulum->kode, $matkul->kode, $semester->kode, 'A', '16', 'Ya', '40', $dosen->kode_dosen, '', '', '', 'Ya'],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 1)
        ->assertSet('result.skip_count', 0);

    $kelas = Kelas::where('id_prodi', $prodi->id)->firstOrFail();
    expect($kelas->kode)->toBe('A');
    expect($kelas->kuota)->toBe(40);
    expect($kelas->id_dosen_pic)->toBe($dosen->id);
    expect((bool) $kelas->is_active)->toBeTrue();
});

it('imports a kelas row using the legacy 6-column format', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['kode' => 'SI']);
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'status' => 'active']);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'MK900']);
    KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id, 'id_matkul' => $matkul->id]);
    $semester = Semester::factory()->create(['kode' => '20242']);

    $file = makeKelasImportFile([
        [$matkul->kode, $prodi->kode, $semester->kode, '', '', ''],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 1);

    expect(Kelas::where('id_prodi', $prodi->id)->where('id_semester', $semester->id)->exists())->toBeTrue();
});

it('skips a kelas row that duplicates an existing combination and records the reason', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['kode' => 'MN']);
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'KUR-MN']);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'MK500']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id, 'id_matkul' => $matkul->id]);
    $semester = Semester::factory()->create(['kode' => '20243']);
    Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_semester' => $semester->id,
        'id_angkatan' => $semester->id,
        'id_kelompok_kelas' => null,
    ]);

    $file = makeKelasImportFile([
        [$prodi->kode, $kurikulum->kode, $matkul->kode, $semester->kode, '', '16', 'Ya', '0', '', '', '', '', 'Tidak'],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 0)
        ->assertSet('result.skip_count', 1);

    expect(Kelas::where('id_prodi', $prodi->id)->count())->toBe(1);
});

it('records an error when the mata kuliah code cannot be found', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['kode' => 'HK']);
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'KUR-HK']);
    $semester = Semester::factory()->create(['kode' => '20244']);

    $file = makeKelasImportFile([
        [$prodi->kode, $kurikulum->kode, 'TIDAK-ADA', $semester->kode, '', '', '', '', '', '', '', '', ''],
    ]);

    $result = Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 0)
        ->get('result');

    expect($result['errors'])->not->toBeEmpty();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.akademik.kelas.import'))
        ->assertRedirect(route('login'));
});
