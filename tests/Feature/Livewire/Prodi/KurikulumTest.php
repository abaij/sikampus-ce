<?php

use App\Livewire\Prodi\Kurikulum\Index;
use App\Livewire\Prodi\Kurikulum\Show;
use App\Models\BobotPenilaian;
use App\Models\JenisPenilaian;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use Livewire\Livewire;

it('lists only kurikulum within the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    Kurikulum::factory()->create(['nama' => 'Kurikulum Prodi A', 'id_prodi' => $prodiA->id]);
    Kurikulum::factory()->create(['nama' => 'Kurikulum Prodi B', 'id_prodi' => $prodiB->id]);

    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Kurikulum Prodi A')
        ->assertDontSee('Kurikulum Prodi B');
});

it('has no delete or create actions available (read-only portal)', function () {
    $prodi = Prodi::factory()->create();
    Kurikulum::factory()->create(['id_prodi' => $prodi->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.kurikulum'))->getContent();

    expect($html)->not->toContain('wire:click="confirmDelete');
    expect($html)->not->toContain('Tambah Kurikulum');
});

it('shows kurikulum detail within scope with its mata kuliah list', function () {
    $prodi = Prodi::factory()->create();
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Kurikulum Detail']);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'MK900']);
    $kurikulum->matkuls()->sync([
        $matkul->id => ['kode_matkul' => $matkul->kode, 'nama_matkul' => $matkul->nama, 'sks' => $matkul->sks, 'semester_rekomendasi' => 4, 'is_wajib' => true],
    ]);
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->get(route('prodi.kurikulum.show', $kurikulum->id))
        ->assertOk()
        ->assertSee('Kurikulum Detail')
        ->assertSee('MK900');
});

it('forbids viewing a kurikulum outside the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kurikulumB = Kurikulum::factory()->create(['id_prodi' => $prodiB->id]);
    $kaprodi = kaprodiUser($prodiA);

    $this->actingAs($kaprodi)
        ->get(route('prodi.kurikulum.show', $kurikulumB->id))
        ->assertStatus(403);
});

it('saves bobot penilaian manually through the kelola bobot modal', function () {
    $prodi = Prodi::factory()->create();
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id]);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $kurikulum->matkuls()->sync([
        $matkul->id => ['kode_matkul' => $matkul->kode, 'nama_matkul' => $matkul->nama, 'sks' => $matkul->sks, 'is_wajib' => true],
    ]);
    $km = KurikulumMatkul::where('id_kurikulum', $kurikulum->id)->where('id_matkul', $matkul->id)->firstOrFail();
    $jenisTugas = JenisPenilaian::factory()->create(['nama' => 'Tugas', 'bobot' => 0]);
    $jenisUas = JenisPenilaian::factory()->create(['nama' => 'UAS', 'bobot' => 0]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Show::class, ['id' => $kurikulum->id])
        ->call('openDetailModal', $km->id)
        ->call('openBobotForm')
        ->set('bobotForm.'.$jenisTugas->id, '30')
        ->set('bobotForm.'.$jenisUas->id, '70')
        ->call('saveBobotForm');

    $rows = BobotPenilaian::where('id_kurikulum_matkul', $km->id)->get()->keyBy('id_jenis_penilaian');
    expect($rows->count())->toBe(2);
    expect((float) $rows[$jenisTugas->id]->bobot)->toBe(30.0);
    expect((float) $rows[$jenisUas->id]->bobot)->toBe(70.0);
});

it('rejects bobot penilaian totaling more than 100 percent', function () {
    $prodi = Prodi::factory()->create();
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id]);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $kurikulum->matkuls()->sync([
        $matkul->id => ['kode_matkul' => $matkul->kode, 'nama_matkul' => $matkul->nama, 'sks' => $matkul->sks, 'is_wajib' => true],
    ]);
    $km = KurikulumMatkul::where('id_kurikulum', $kurikulum->id)->where('id_matkul', $matkul->id)->firstOrFail();
    $jenis = JenisPenilaian::factory()->create(['bobot' => 0]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Show::class, ['id' => $kurikulum->id])
        ->call('openDetailModal', $km->id)
        ->call('openBobotForm')
        ->set('bobotForm.'.$jenis->id, '150')
        ->call('saveBobotForm')
        ->assertHasErrors('bobotForm');

    expect(BobotPenilaian::where('id_kurikulum_matkul', $km->id)->count())->toBe(0);
});

it('auto-fills bobot penilaian from jenis penilaian defaults', function () {
    $prodi = Prodi::factory()->create();
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id]);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $kurikulum->matkuls()->sync([
        $matkul->id => ['kode_matkul' => $matkul->kode, 'nama_matkul' => $matkul->nama, 'sks' => $matkul->sks, 'is_wajib' => true],
    ]);
    $km = KurikulumMatkul::where('id_kurikulum', $kurikulum->id)->where('id_matkul', $matkul->id)->firstOrFail();
    $jenisA = JenisPenilaian::factory()->create(['nama' => 'Kehadiran', 'bobot' => 10]);
    $jenisB = JenisPenilaian::factory()->create(['nama' => 'UTS', 'bobot' => 30]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Show::class, ['id' => $kurikulum->id])
        ->call('openDetailModal', $km->id)
        ->call('openAutoFillConfirm')
        ->call('confirmAutoFill');

    $rows = BobotPenilaian::where('id_kurikulum_matkul', $km->id)->get()->keyBy('id_jenis_penilaian');
    expect($rows->count())->toBe(2);
    expect((float) $rows[$jenisA->id]->bobot)->toBe(10.0);
    expect((float) $rows[$jenisB->id]->bobot)->toBe(30.0);
});

it('has no sync-from-master, bobot massal, edit, or delete actions (not exposed under /prodi/*)', function () {
    $prodi = Prodi::factory()->create();
    $kurikulum = Kurikulum::factory()->create(['id_prodi' => $prodi->id]);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $kurikulum->matkuls()->sync([
        $matkul->id => ['kode_matkul' => $matkul->kode, 'nama_matkul' => $matkul->nama, 'sks' => $matkul->sks, 'is_wajib' => true],
    ]);
    $km = KurikulumMatkul::where('id_kurikulum', $kurikulum->id)->where('id_matkul', $matkul->id)->firstOrFail();
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.kurikulum.show', $kurikulum->id))->getContent();

    expect($html)->not->toContain('Sinkronisasi Mata Kuliah');
    expect($html)->not->toContain('Tetapkan Bobot Massal');
    expect($html)->not->toContain('wire:click="confirmDeleteKurikulum"');
    expect($html)->not->toContain('Ubah');

    $component = Livewire::actingAs($kaprodi)->test(Show::class, ['id' => $kurikulum->id]);
    expect(method_exists($component->instance(), 'syncMatkulFromMaster'))->toBeFalse();
    expect(method_exists($component->instance(), 'saveBobotMassalForm'))->toBeFalse();
    expect(method_exists($component->instance(), 'deleteKurikulum'))->toBeFalse();

    // sanity: modal masih bisa dibuka meski aksi tulis lain tidak ada.
    $component->call('openDetailModal', $km->id)->assertOk();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.kurikulum'))->assertRedirect(route('login'));
});
