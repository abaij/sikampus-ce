<?php

use App\Livewire\Admin\Nilai\Form;
use App\Livewire\Admin\Nilai\Show;
use App\Models\Jenjang;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\RentangNilai;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view nilai but not reach the edit route', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    $this->actingAs($admin)->get(route('admin.akademik.nilai'))->assertOk();
    $this->actingAs($admin)->get(route('admin.akademik.nilai.show', $mahasiswa->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.akademik.nilai.edit', [$mahasiswa->id, $krs->id]))->assertStatus(403);
});

it('still allows export and cetak for a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    Nilai::factory()->create(['id_krs' => $krs->id, 'is_final' => true]);

    $this->actingAs($admin)->get(route('admin.akademik.nilai.export', $mahasiswa->id))->assertOk();
    $this->actingAs($admin)->get(route('admin.akademik.nilai.cetak', $mahasiswa->id))->assertOk();
});

it('hides the ubah/hapus nilai buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    Nilai::factory()->create(['id_krs' => $krs->id]);

    $this->actingAs($admin)->get(route('admin.akademik.nilai.show', $mahasiswa->id))
        ->assertOk()
        ->assertDontSee(route('admin.akademik.nilai.edit', [$mahasiswa->id, $krs->id]));
});

it('blocks a view-only akademik admin from deleting nilai via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $nilai->id)
        ->assertStatus(403);

    expect(Nilai::find($nilai->id))->not->toBeNull();
});

it('lets an akademik admin input, correct, and delete nilai once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['update nilai', 'delete nilai']);

    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A', 'nilai_angka' => 4]);

    $this->actingAs($admin)->get(route('admin.akademik.nilai.edit', [$mahasiswa->id, $krs->id]))->assertOk();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id, 'idKrs' => $krs->id])
        ->set('huruf_mutu', 'A')
        ->set('is_final', true)
        ->call('save')
        ->assertRedirect(route('admin.akademik.nilai.show', $mahasiswa->id));

    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('A');

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $nilai->id)
        ->call('delete');

    expect(Nilai::find($nilai->id))->toBeNull();
});

it('still lets superadmin do everything on nilai regardless of granular mode', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id]);

    $this->actingAs($admin)->get(route('admin.akademik.nilai.edit', [$mahasiswa->id, $krs->id]))->assertOk();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $nilai->id)
        ->call('delete');

    expect(Nilai::find($nilai->id))->toBeNull();
});

it('still blocks keuangan-only admins from nilai entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($admin)->get(route('admin.akademik.nilai'))->assertStatus(403);
});
