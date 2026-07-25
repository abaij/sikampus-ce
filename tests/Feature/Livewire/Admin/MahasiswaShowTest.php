<?php

use App\Livewire\Admin\Mahasiswa\Index;
use App\Livewire\Admin\Mahasiswa\Show;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Livewire;

it('shows a detail link (not a delete button) for each row on the index page', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa'))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee(route('admin.administrasi.mahasiswa.show', $mahasiswa->id))
        ->assertDontSee('Hapus');

    expect(method_exists(Index::class, 'delete'))->toBeFalse();
});

it('renders the mahasiswa detail page with biodata tab by default', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari', 'nim' => '2024999888']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.show', $mahasiswa->id))
        ->assertOk()
        ->assertSee('Citra Lestari')
        ->assertSee('2024999888');
});

it('shows krs and nilai grouped by semester', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create(['kode' => '20241', 'nama' => 'Ganjil 2024/2025']);
    $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);
    $krs = Krs::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kelas' => $kelas->id,
        'approved_at' => now(),
        'approved_by' => 'admin',
    ]);
    Nilai::factory()->create(['id_krs' => $krs->id, 'angka_mutu' => 4, 'huruf_mutu' => 'A']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('activeTab', 'krs')
        ->assertSee('Ganjil 2024/2025')
        ->set('activeTab', 'nilai')
        ->assertSee('Ganjil 2024/2025')
        ->assertSee('A');
});

it('deletes a mahasiswa from the detail page after confirmation', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDeleteMahasiswa')
        ->call('deleteMahasiswa')
        ->assertRedirect(route('admin.administrasi.mahasiswa'));

    expect(Mahasiswa::find($mahasiswa->id))->toBeNull();
});

it('admin dengan scope prodi tidak bisa membuka detail mahasiswa di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.show', $mahasiswaB->id))
        ->assertForbidden();
});

it('redirects unauthenticated users to the admin login page', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $this->get(route('admin.administrasi.mahasiswa.show', $mahasiswa->id))
        ->assertRedirect(route('admin.login'));
});
