<?php

use App\Livewire\Admin\Kelas\Form;
use App\Livewire\Admin\Kelas\Index;
use App\Livewire\Admin\Kelas\Show;
use App\Models\Dosen;
use App\Models\Jenjang;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\KelompokKelas;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Livewire;

it('renders index, create form, and show page', function () {
    $admin = adminUser();
    $matkul = Matkul::factory()->create(['nama' => 'Pemrograman Web', 'kode' => 'IF101']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id]);

    $this->actingAs($admin)->get(route('admin.akademik.kelas'))->assertOk()->assertSee('Pemrograman Web');
    $this->actingAs($admin)->get(route('admin.akademik.kelas.create'))->assertOk()->assertSee('Tambah Kelas');
    $this->actingAs($admin)->get(route('admin.akademik.kelas.show', $kelas->id))->assertOk()->assertSee('Pemrograman Web');
});

it('creates, updates, and deletes a kelas', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester1 = Semester::factory()->create(['kode' => '20231']);
    $semester2 = Semester::factory()->create(['kode' => '20241']);
    $dosen = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester2->id)
        ->set('id_angkatan', $semester1->id)
        ->set('id_dosen_pic', $dosen->id)
        ->set('kode', 'A')
        ->call('save')
        ->assertRedirect(route('admin.akademik.kelas'));

    $kelas = Kelas::where('kode', 'A')->firstOrFail();
    expect($kelas->id_dosen_pic)->toBe($dosen->id);
    expect(KelasDosen::where('id_kelas', $kelas->id)->where('id_dosen', $dosen->id)->where('is_pic', true)->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->assertSet('kode', 'A')
        ->set('kuota', '40')
        ->call('save');

    expect($kelas->fresh()->kuota)->toBe(40);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelas->id)
        ->call('delete');

    expect(Kelas::find($kelas->id))->toBeNull();
});

it('rejects a duplicate kombinasi kurikulum matkul, semester, dan angkatan', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester = Semester::factory()->create();

    Kelas::factory()->create([
        'id_prodi' => $prodi->id,
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
        'id_angkatan' => $semester->id,
        'id_kelompok_kelas' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester->id)
        ->set('id_angkatan', $semester->id)
        ->call('save')
        ->assertHasErrors(['id_kurikulum_matkul']);
});

it('shows all kelas mahasiswa filter options when no prodi is selected, and scopes them once one is', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    KelompokKelas::factory()->create(['nama' => 'Kelompok A', 'id_prodi' => $prodiA->id]);
    KelompokKelas::factory()->create(['nama' => 'Kelompok B', 'id_prodi' => $prodiB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kelompok A')
        ->assertSee('Kelompok B');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodiA->id)
        ->assertSee('Kelompok A')
        ->assertDontSee('Kelompok B');
});

it('displays prodi filter options with the jenjang code in parentheses', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create(['kode' => 'D3']);
    Prodi::factory()->create(['nama' => 'Kebidanan', 'id_jenjang' => $jenjang->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kebidanan (D3)');
});

it('displays semester filter options as name with the code in parentheses', function () {
    $admin = adminUser();
    Semester::factory()->create(['nama' => '2025 Ganjil', 'kode' => '20251']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('2025 Ganjil (20251)');
});

it('admin dengan scope prodi hanya melihat kelas miliknya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['nama' => 'Kelas Prodi A']);
    $matkulB = Matkul::factory()->create(['nama' => 'Kelas Prodi B']);
    Kelas::factory()->create([
        'id_prodi' => $prodiA->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]),
    ]);
    Kelas::factory()->create([
        'id_prodi' => $prodiB->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]),
    ]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kelas Prodi A')
        ->assertDontSee('Kelas Prodi B');
});

it('admin dengan scope prodi tidak bisa menghapus kelas di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelasB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Kelas::find($kelasB->id))->not->toBeNull();
});

it('admin dengan scope prodi tidak bisa membuka detail kelas di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelasB->id])
        ->assertStatus(403);
});

it('carries the current page/filter state from index into the Lihat and Ubah links', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    Kelas::factory()->count(15)->create(['id_prodi' => $prodi->id]);

    $expectedQuery = 'id_prodi='.$prodi->id.'&page=2';

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodi->id)
        ->set('filterSemester', '')
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee($expectedQuery);
});

it('points the Kembali button on the detail page to the page/filter state carried in the query string', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.show', $kelas->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.akademik.kelas').'?page=2&search=algoritma')
        ->assertDontSee('unexpected=1');
});

it('carries the forwarded state into the Ubah link on the detail page too', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.show', $kelas->id).'?page=2&search=algoritma')
        ->assertOk()
        ->assertSee(route('admin.akademik.kelas.edit', $kelas->id).'?page=2&search=algoritma');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $expectedBackUrl = route('admin.akademik.kelas').'?page=2&search=algoritma';

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.edit', $kelas->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'algoritma'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->set('kuota', '30')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.kelas'))->assertRedirect(route('login'));
});

// Regression: layouts.web me-render @section('page_actions') di luar root <div> komponen, jadi
// tombol wire:click yang diletakkan di sana tidak pernah terikat Livewire dan diam saja saat diklik.
it('keeps the delete button inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.akademik.kelas.show', $kelas->id))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();
    expect(strpos($html, 'wire:click="confirmDelete"'))->toBeGreaterThan($rootStart);
});
