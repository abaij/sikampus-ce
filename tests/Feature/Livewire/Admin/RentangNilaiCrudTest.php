<?php

use App\Livewire\Admin\RentangNilai\Form;
use App\Livewire\Admin\RentangNilai\Index;
use App\Models\Jenjang;
use App\Models\RentangNilai;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create(['nama' => 'Sarjana Uji']);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A']);

    $this->actingAs($admin)->get(route('admin.akademik.rentang-nilai'))->assertOk()->assertSee('Sarjana Uji')->assertSee('A');
    $this->actingAs($admin)->get(route('admin.akademik.rentang-nilai.create'))->assertOk()->assertSee('Tambah Rentang Nilai');
});

it('filters index by jenjang', function () {
    $admin = adminUser();
    $jenjangA = Jenjang::factory()->create(['nama' => 'Jenjang A']);
    $jenjangB = Jenjang::factory()->create(['nama' => 'Jenjang B']);
    // Huruf dibuat unik (bukan hanya "A"/"B") supaya tidak ambigu dengan label jenjang yang selalu
    // muncul di dropdown filter terlepas dari filter yang aktif.
    RentangNilai::factory()->create(['id_jenjang' => $jenjangA->id, 'nilai_huruf' => 'ZA']);
    RentangNilai::factory()->create(['id_jenjang' => $jenjangB->id, 'nilai_huruf' => 'ZB']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterJenjang', (string) $jenjangA->id)
        ->assertSee('ZA')
        ->assertDontSee('ZB');
});

it('creates multiple rentang nilai rows in one batch submit', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_jenjang', $jenjang->id)
        ->set('baris.0.nilai_huruf', 'A')
        ->set('baris.0.nilai_angka', '4')
        ->set('baris.0.nilai_rendah', '85')
        ->set('baris.0.nilai_tinggi', '100')
        ->call('addRow')
        ->set('baris.1.nilai_huruf', 'B')
        ->set('baris.1.nilai_angka', '3')
        ->set('baris.1.nilai_rendah', '70')
        ->set('baris.1.nilai_tinggi', '84.99')
        ->call('save')
        ->assertRedirect(route('admin.akademik.rentang-nilai'));

    expect(RentangNilai::where('id_jenjang', $jenjang->id)->count())->toBe(2);
    expect(RentangNilai::where('id_jenjang', $jenjang->id)->where('nilai_huruf', 'A')->exists())->toBeTrue();
    expect(RentangNilai::where('id_jenjang', $jenjang->id)->where('nilai_huruf', 'B')->exists())->toBeTrue();
});

it('rejects duplicate huruf within the same batch submit and keeps prior rows saved', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_jenjang', $jenjang->id)
        ->set('baris.0.nilai_huruf', 'A')
        ->set('baris.0.nilai_angka', '4')
        ->set('baris.0.nilai_rendah', '85')
        ->set('baris.0.nilai_tinggi', '100')
        ->call('addRow')
        ->set('baris.1.nilai_huruf', 'A')
        ->set('baris.1.nilai_angka', '4')
        ->set('baris.1.nilai_rendah', '85')
        ->set('baris.1.nilai_tinggi', '100')
        ->call('save');

    expect(RentangNilai::where('id_jenjang', $jenjang->id)->count())->toBe(1);
});

it('rejects a batch row where nilai_tinggi is less than nilai_rendah', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_jenjang', $jenjang->id)
        ->set('baris.0.nilai_huruf', 'A')
        ->set('baris.0.nilai_angka', '4')
        ->set('baris.0.nilai_rendah', '90')
        ->set('baris.0.nilai_tinggi', '80')
        ->call('save');

    expect(RentangNilai::where('id_jenjang', $jenjang->id)->count())->toBe(0);
});

it('updates a rentang nilai row via the edit form', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create();
    $rentangNilai = RentangNilai::factory()->create([
        'id_jenjang' => $jenjang->id,
        'nilai_huruf' => 'A',
        'nilai_angka' => 4,
        'nilai_rendah' => 85,
        'nilai_tinggi' => 100,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $rentangNilai->id])
        ->assertSet('nilai_huruf', 'A')
        ->set('nilai_huruf', 'A-')
        ->set('nilai_angka', '3.7')
        ->call('save')
        ->assertRedirect(route('admin.akademik.rentang-nilai'));

    $rentangNilai->refresh();
    expect($rentangNilai->nilai_huruf)->toBe('A-');
    expect((float) $rentangNilai->nilai_angka)->toBe(3.7);
});

it('blocks updating to a huruf that already exists for the same jenjang', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create();
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A']);
    $rentangNilaiB = RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B', 'nilai_rendah' => 70, 'nilai_tinggi' => 84]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $rentangNilaiB->id])
        ->set('nilai_huruf', 'A')
        ->call('save')
        ->assertHasErrors(['nilai_huruf']);

    expect($rentangNilaiB->fresh()->nilai_huruf)->toBe('B');
});

it('deletes a rentang nilai row from the index page', function () {
    $admin = adminUser();
    $rentangNilai = RentangNilai::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $rentangNilai->id)
        ->call('delete');

    expect(RentangNilai::find($rentangNilai->id))->toBeNull();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.akademik.rentang-nilai'))->assertRedirect(route('login'));
});
