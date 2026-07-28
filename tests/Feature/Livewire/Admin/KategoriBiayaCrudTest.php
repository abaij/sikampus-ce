<?php

use App\Livewire\Admin\KategoriBiaya\Form;
use App\Livewire\Admin\KategoriBiaya\Index;
use App\Livewire\Admin\KategoriBiaya\Show;
use App\Models\KategoriBiaya;
use App\Models\KategoriBiayaMahasiswa;
use App\Models\Mahasiswa;
use App\Models\Semester;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    KategoriBiaya::factory()->create(['nama' => 'Beasiswa Prestasi']);

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya'))->assertOk()->assertSee('Beasiswa Prestasi');
    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.create'))->assertOk()->assertSee('Tambah Kategori Biaya');
});

it('creates, updates, and deletes a kategori biaya', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Reguler')
        ->set('kode', 'REG')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.kategori-biaya'));

    $kategoriBiaya = KategoriBiaya::where('nama', 'Reguler')->firstOrFail();
    expect($kategoriBiaya->kode)->toBe('REG');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kategoriBiaya->id])
        ->assertSet('nama', 'Reguler')
        ->set('nama', 'Reguler S1')
        ->call('save');

    expect($kategoriBiaya->fresh()->nama)->toBe('Reguler S1');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kategoriBiaya->id)
        ->call('delete');

    expect(KategoriBiaya::find($kategoriBiaya->id))->toBeNull();
});

it('rejects a duplicate kode when creating a kategori biaya', function () {
    $admin = adminUser();
    KategoriBiaya::factory()->create(['kode' => 'REG']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Reguler Baru')
        ->set('kode', 'REG')
        ->call('save')
        ->assertHasErrors('kode');
});

it('shows a kategori biaya with its assigned mahasiswa', function () {
    $admin = adminUser();
    $kategoriBiaya = KategoriBiaya::factory()->create(['nama' => 'Beasiswa Prestasi']);
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari']);
    KategoriBiayaMahasiswa::factory()->create([
        'id_kategori_biaya' => $kategoriBiaya->id,
        'id_mahasiswa' => $mahasiswa->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.keuangan.kategori-biaya.show', $kategoriBiaya->id))
        ->assertOk()
        ->assertSee('Citra Lestari');
});

it('adds a mahasiswa to a kategori biaya and deactivates their previous active assignment', function () {
    $admin = adminUser();
    $kategoriLama = KategoriBiaya::factory()->create();
    $kategoriBaru = KategoriBiaya::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create(['is_active' => true]);

    $assignmentLama = KategoriBiayaMahasiswa::factory()->create([
        'id_kategori_biaya' => $kategoriLama->id,
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBaru->id])
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->set('selectedSemesterId', (string) $semester->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($assignmentLama->fresh()->status)->toBe('inactive');
    expect(KategoriBiayaMahasiswa::where('id_kategori_biaya', $kategoriBaru->id)
        ->where('id_mahasiswa', $mahasiswa->id)
        ->where('status', 'active')
        ->exists())->toBeTrue();
});

it('rejects adding the same mahasiswa/semester combination twice', function () {
    $admin = adminUser();
    $kategoriBiaya = KategoriBiaya::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();

    KategoriBiayaMahasiswa::factory()->create([
        'id_kategori_biaya' => $kategoriBiaya->id,
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBiaya->id])
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->set('selectedSemesterId', (string) $semester->id)
        ->call('save')
        ->assertHasErrors('selectedMahasiswaId');
});

it('paginates the mahasiswa list on the show page using a dedicated mhs_page query parameter', function () {
    $admin = adminUser();
    $kategoriBiaya = KategoriBiaya::factory()->create();
    Mahasiswa::factory()->count(15)->create()->each(function (Mahasiswa $mahasiswa) use ($kategoriBiaya) {
        KategoriBiayaMahasiswa::factory()->create([
            'id_kategori_biaya' => $kategoriBiaya->id,
            'id_mahasiswa' => $mahasiswa->id,
            'status' => 'active',
        ]);
    });

    // pageName 'mhs_page' (bukan default 'page') — lihat catatan di Show::updatingSearch().
    // Memanggil gotoPage dengan nama default 'page' TIDAK BOLEH memindahkan paginator ini,
    // supaya kalau nanti halaman ini punya paginator lain (atau state 'page' dari Index ikut
    // terbawa di URL), keduanya tidak saling menimpa.
    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBiaya->id])
        ->call('gotoPage', 2, 'page');
    expect($component->instance()->mahasiswaList->currentPage())->toBe(1);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBiaya->id])
        ->call('gotoPage', 2, 'mhs_page');
    expect($component->instance()->mahasiswaList->currentPage())->toBe(2);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.kategori-biaya'))->assertRedirect(route('login'));
});
