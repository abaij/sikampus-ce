<?php

use App\Livewire\Admin\Pembayaran\Form;
use App\Livewire\Admin\Pembayaran\Show;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view pembayaran but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $pembayaran = Pembayaran::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.show', $pembayaran->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.edit', $pembayaran->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus/acc buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $pembayaran = Pembayaran::factory()->create(['approved_at' => null]);

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran'))
        ->assertOk()
        ->assertDontSee('Tambah Pembayaran');

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.show', $pembayaran->id))
        ->assertOk()
        ->assertDontSee(route('admin.keuangan.pembayaran.edit', $pembayaran->id))
        ->assertDontSee('ACC Pembayaran');
});

it('blocks a view-only keuangan admin from deleting or approving via the livewire methods directly', function () {
    $admin = adminUser('admin_keuangan');
    $pembayaran = Pembayaran::factory()->create(['approved_at' => null]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('confirmDelete')
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('approve')
        ->assertStatus(403);

    expect(Pembayaran::find($pembayaran->id)->approved_at)->toBeNull();
});

it('lets a keuangan admin create, edit, delete, and approve pembayaran once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create pembayaran', 'update pembayaran', 'delete pembayaran', 'approve pembayaran']);

    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2023000999']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'status' => 'unpaid']);

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.create'))
        ->assertOk()
        ->assertSee('Tambah Pembayaran');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nim', '2023000999')
        ->call('selectTagihan', $tagihan->id)
        ->set('nominal', '400000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.pembayaran'));

    // Form::saveCreate selalu langsung approved_at = now() (lihat komentar di PembayaranCrudTest
    // "auto-approves it"), jadi approve() diuji terpisah lewat record yang sengaja belum disetujui.
    $pembayaran = Pembayaran::where('id_tagihan', $tagihan->id)->firstOrFail();
    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.edit', $pembayaran->id))->assertOk();

    $pembayaranBelumAcc = Pembayaran::factory()->create(['approved_at' => null]);
    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaranBelumAcc->id])
        ->call('approve')
        ->assertHasNoErrors();

    expect($pembayaranBelumAcc->fresh()->approved_at)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('confirmDelete')
        ->call('delete');

    expect(Pembayaran::find($pembayaran->id))->toBeNull();
});

it('still lets superadmin do everything on pembayaran regardless of granular mode', function () {
    $admin = adminUser();
    $pembayaran = Pembayaran::factory()->create(['approved_at' => null]);

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran.edit', $pembayaran->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('approve')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pembayaran->id])
        ->call('confirmDelete')
        ->call('delete');

    expect(Pembayaran::find($pembayaran->id))->toBeNull();
});

it('still blocks akademik-only admins from pembayaran entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.pembayaran'))->assertStatus(403);
});
