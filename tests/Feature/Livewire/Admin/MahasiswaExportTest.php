<?php

use App\Models\Mahasiswa;
use App\Models\Prodi;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Simpan body streamed response ke file sementara lalu baca isinya sebagai baris teks —
 * dipakai untuk memastikan hasil export benar-benar mencerminkan filter yang dikirim.
 */
function readMahasiswaExportRows($response): array
{
    $path = tempnam(sys_get_temp_dir(), 'mhs_export_').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $rows = IOFactory::load($path)->getActiveSheet()->toArray();

    return array_map(fn ($row) => implode(' | ', array_filter($row, fn ($v) => $v !== null && $v !== '')), $rows);
}

it('shows an export link on the index page that carries the current filters', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa'))
        ->assertOk()
        ->assertSee('Export Excel')
        ->assertSee(route('admin.administrasi.mahasiswa.export'), false);
});

it('exports as an xlsx file', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('only exports mahasiswa matching the selected prodi filter', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi B']);
    Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi A', 'id_prodi' => $prodiA->id]);
    Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi B', 'id_prodi' => $prodiB->id]);

    $response = $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export', ['id_prodi' => $prodiA->id]));

    $text = implode("\n", readMahasiswaExportRows($response));

    expect($text)->toContain('Mahasiswa Prodi A');
    expect($text)->not->toContain('Mahasiswa Prodi B');
});

it('only exports mahasiswa matching the search filter', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Findable Student', 'nim' => '2024000111']);
    Mahasiswa::factory()->create(['nama' => 'Other Student', 'nim' => '2024000222']);

    $response = $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export', ['search' => 'Findable']));

    $text = implode("\n", readMahasiswaExportRows($response));

    expect($text)->toContain('Findable Student');
    expect($text)->not->toContain('Other Student');
});

it('admin dengan scope prodi hanya bisa mengexport mahasiswa dari prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    Mahasiswa::factory()->create(['nama' => 'Dalam Scope', 'id_prodi' => $prodiA->id]);
    Mahasiswa::factory()->create(['nama' => 'Luar Scope', 'id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $response = $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export'));

    $text = implode("\n", readMahasiswaExportRows($response));

    expect($text)->toContain('Dalam Scope');
    expect($text)->not->toContain('Luar Scope');
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.administrasi.mahasiswa.export'))
        ->assertRedirect(route('login'));
});
