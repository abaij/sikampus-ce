<?php

use App\Livewire\Admin\Pembayaran\Form;
use App\Livewire\Admin\Pembayaran\Index;
use App\Livewire\Admin\Pembayaran\Show;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Models\User;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'approved_at' => now()]);

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran'))->assertOk()->assertSee('Citra Lestari');
    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.create'))->assertOk()->assertSee('Tambah Pembayaran');
});

it('creates a pembayaran, auto-approves it, and marks the tagihan paid when fully covered', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2023000111']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'status' => 'unpaid']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nim', '2023000111')
        ->call('selectTagihan', $tagihan->id)
        ->assertSet('nominal', '1000000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.pembayaran'));

    $pembayaran = Pembayaran::where('id_tagihan', $tagihan->id)->firstOrFail();
    expect($pembayaran->no_pembayaran)->toStartWith('PAY-'.now()->format('Ymd').'-');
    expect($pembayaran->approved_at)->not->toBeNull();
    expect((float) $pembayaran->nominal)->toBe(1000000.0);
    expect($tagihan->fresh()->status)->toBe('paid');
});

it('rejects a nominal greater than the sisa tagihan when creating', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2023000222']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nim', '2023000222')
        ->call('selectTagihan', $tagihan->id)
        ->set('nominal', '999999')
        ->call('save')
        ->assertHasErrors('nominal');

    expect(Pembayaran::where('id_tagihan', $tagihan->id)->exists())->toBeFalse();
});

it('rejects creating a payment for an already fully paid tagihan', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2023000333']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000, 'status' => 'unpaid']);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 500000, 'approved_at' => now()]);

    // Tagihan lunas tidak boleh muncul di daftar tagihan yang belum lunas untuk NIM ini.
    $result = Livewire::actingAs($admin)->test(Form::class)->set('nim', '2023000333');
    expect($result->instance()->mahasiswaTagihan['tagihan'])->toBeEmpty();
});

it('updates a pembayaran nominal and recomputes the tagihan status', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create(['total' => 1000000, 'status' => 'paid']);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 1000000, 'approved_at' => now()]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pembayaran->id])
        ->assertSet('nominal', '1000000.00')
        ->set('nominal', '400000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.pembayaran'));

    expect((float) $pembayaran->fresh()->nominal)->toBe(400000.0);
    expect($tagihan->fresh()->status)->toBe('unpaid');
});

it('rejects an update nominal that exceeds the tagihan total combined with other approved payments', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 700000, 'approved_at' => now()]);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 300000, 'approved_at' => now()]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pembayaran->id])
        ->set('nominal', '400000')
        ->call('save')
        ->assertHasErrors('nominal');

    expect((float) $pembayaran->fresh()->nominal)->toBe(300000.0);
});

it('shows the pembayaran detail page with tagihan and mahasiswa info', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id]);

    $this->actingAs($admin)
        ->get(route('admin.keuangan.pembayaran.show', $pembayaran->id))
        ->assertOk()
        ->assertSee($pembayaran->no_pembayaran)
        ->assertSee('Budi Santoso');
});

it('approves a pending pembayaran, marks the tagihan paid, and notifies the mahasiswa', function () {
    $admin = adminUser();
    $user = User::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000, 'status' => 'unpaid']);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 500000, 'approved_at' => null]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('approve')
        ->assertHasNoErrors();

    expect($pembayaran->fresh()->approved_at)->not->toBeNull();
    expect($tagihan->fresh()->status)->toBe('paid');
    expect(Notifikasi::where('id_user', $user->id)->where('tipe', 'pembayaran_acc')->exists())->toBeTrue();
});

it('rejects approving a pembayaran that was already approved', function () {
    $admin = adminUser();
    $pembayaran = Pembayaran::factory()->create(['approved_at' => now()]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('approve')
        ->assertHasErrors('approve');
});

it('deletes a pembayaran and recomputes the tagihan status', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create(['total' => 500000, 'status' => 'paid']);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 500000, 'approved_at' => now()]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('confirmDelete')
        ->call('delete')
        ->assertRedirect(route('admin.keuangan.pembayaran'));

    expect(Pembayaran::find($pembayaran->id))->toBeNull();
    expect($tagihan->fresh()->status)->toBe('unpaid');
});

it('filters the index by prodi even without scope restriction, since pembayaran has no scope enforcement', function () {
    $admin = adminUser('admin_keuangan');
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Filter A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Filter B']);
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa Prodi A']);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa Prodi B']);
    Pembayaran::factory()->create(['id_tagihan' => Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaA->id])->id]);
    Pembayaran::factory()->create(['id_tagihan' => Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id])->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodiA->id)
        ->assertSee('Mahasiswa Prodi A')
        ->assertDontSee('Mahasiswa Prodi B');
});

it('defaults the periode filter to the active semester and can be cleared to show all periods', function () {
    $admin = adminUser();
    $periodeAktif = Semester::factory()->create(['is_active' => true]);
    $periodeLain = Semester::factory()->create(['is_active' => false]);

    Pembayaran::factory()->create([
        'id_tagihan' => Tagihan::factory()->create(['id_semester' => $periodeAktif->id])->id,
        'nominal' => 1111111,
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => Tagihan::factory()->create(['id_semester' => $periodeLain->id])->id,
        'nominal' => 2222222,
    ]);

    $component = Livewire::actingAs($admin)->test(Index::class);
    expect($component->get('filterSemester'))->toBe((string) $periodeAktif->id);
    $component->assertSee('Rp1.111.111')->assertDontSee('Rp2.222.222');

    $component->set('filterSemester', '');
    $component->assertSee('Rp1.111.111')->assertSee('Rp2.222.222');
});

it('carries the current page/filter state from index into the Detail link', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    Pembayaran::factory()->count(15)->create(['id_tagihan' => Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id])->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodi->id)
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee('id_prodi='.$prodi->id.'&page=2');
});

it('points the Kembali button on the show page to the page/search state carried in the query string', function () {
    $admin = adminUser();
    $pembayaran = Pembayaran::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.keuangan.pembayaran.show', $pembayaran->id).'?page=2&search=budi&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.keuangan.pembayaran').'?page=2&search=budi')
        ->assertDontSee('unexpected=1');
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.pembayaran'))->assertRedirect(route('login'));
});
