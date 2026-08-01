<?php

use App\Livewire\Admin\AturanAksesKeuangan\Form;
use App\Livewire\Admin\AturanAksesKeuangan\Index;
use App\Models\AturanAksesKeuangan;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view aturan akses keuangan but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $aturan = AturanAksesKeuangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan'))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.edit', $aturan->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $aturan = AturanAksesKeuangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan'))
        ->assertOk()
        ->assertDontSee('Tambah Aturan')
        ->assertDontSee(route('admin.keuangan.aturan-akses-keuangan.edit', $aturan->id));
});

it('blocks a view-only keuangan admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_keuangan');
    $aturan = AturanAksesKeuangan::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $aturan->id)
        ->assertStatus(403);

    expect(AturanAksesKeuangan::find($aturan->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, and delete aturan akses keuangan once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create aturan akses keuangan', 'update aturan akses keuangan', 'delete aturan akses keuangan']);

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.create'))
        ->assertOk()
        ->assertSee('Tambah Aturan');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode_akses', 'uas_semester')
        ->set('nama', 'Ujian Akhir Semester')
        ->set('persentase_minimum', '80')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.aturan-akses-keuangan'));

    $aturan = AturanAksesKeuangan::where('kode_akses', 'uas_semester')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.edit', $aturan->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $aturan->id)
        ->call('delete');

    expect(AturanAksesKeuangan::find($aturan->id))->toBeNull();
});

it('still lets superadmin do everything on aturan akses keuangan regardless of granular mode', function () {
    $admin = adminUser();
    $aturan = AturanAksesKeuangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan.edit', $aturan->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $aturan->id)
        ->call('delete');

    expect(AturanAksesKeuangan::find($aturan->id))->toBeNull();
});

it('still blocks akademik-only admins from aturan akses keuangan entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.aturan-akses-keuangan'))->assertStatus(403);
});
