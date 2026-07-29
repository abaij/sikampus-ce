<?php

use App\Livewire\Admin\Pembayaran\Form as PembayaranForm;
use App\Livewire\Admin\Pembayaran\Show as PembayaranShow;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Semester;
use App\Models\Tagihan;
use Livewire\Livewire;

/**
 * Inti temuan 1.2: kolom `status` pada tagihan pernah bisa berubah jadi 'paid' hanya karena ada
 * bukti bayar yang diunggah mahasiswa dan belum diverifikasi. Semua jalur yang menyetel status
 * kini lewat Tagihan::lunasMenurutPembayaranDisetujui().
 */
it('does not mark a tagihan paid from payments that are still awaiting approval', function () {
    $tagihan = Tagihan::factory()->create(['total' => 1000000, 'status' => 'unpaid']);

    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 1000000,
        'approved_at' => null,
    ]);

    expect($tagihan->fresh()->lunasMenurutPembayaranDisetujui())->toBeFalse();
    expect($tagihan->fresh()->status)->toBe('unpaid');
});

it('marks a tagihan paid only once the payments are approved', function () {
    $tagihan = Tagihan::factory()->create(['total' => 1000000, 'status' => 'unpaid']);

    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 1000000,
        'approved_at' => null,
    ]);
    expect($tagihan->fresh()->lunasMenurutPembayaranDisetujui())->toBeFalse();

    $pembayaran->update(['approved_at' => now()]);
    expect($tagihan->fresh()->lunasMenurutPembayaranDisetujui())->toBeTrue();
});

it('ignores soft-deleted payments when deciding whether a tagihan is paid', function () {
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 1000000,
        'approved_at' => now(),
    ]);

    expect($tagihan->fresh()->lunasMenurutPembayaranDisetujui())->toBeTrue();

    $pembayaran->delete();
    expect($tagihan->fresh()->lunasMenurutPembayaranDisetujui())->toBeFalse();
});

/**
 * Ini jalur yang dulu bocor: admin mencatat pembayaran sebagian, sementara mahasiswa sudah
 * mengunggah bukti untuk sisanya yang belum diverifikasi. Dulu keduanya dijumlahkan tanpa
 * menyaring approved_at, sehingga tagihan langsung berstatus lunas.
 */
it('keeps a tagihan unpaid when an admin payment plus a pending upload would cover it', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
        'status' => 'unpaid',
    ]);

    // Bukti unggahan mahasiswa yang masih menunggu verifikasi.
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 600000,
        'approved_at' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(PembayaranForm::class)
        ->set('nim', $mahasiswa->nim)
        ->call('selectTagihan', $tagihan->id)
        ->set('nominal', '400000')
        ->call('save')
        ->assertHasNoErrors();

    expect($tagihan->fresh()->status)->toBe('unpaid');
    expect($tagihan->fresh()->tanggal_pembayaran)->toBeNull();
});

it('marks the tagihan paid through the admin form once approved payments cover it', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'total' => 1000000,
        'status' => 'unpaid',
    ]);

    Livewire::actingAs($admin)
        ->test(PembayaranForm::class)
        ->set('nim', $mahasiswa->nim)
        ->call('selectTagihan', $tagihan->id)
        ->set('nominal', '1000000')
        ->call('save')
        ->assertHasNoErrors();

    expect($tagihan->fresh()->status)->toBe('paid');
});

it('flips the tagihan to paid only when the pending payment is approved from the show page', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create(['total' => 1000000, 'status' => 'unpaid']);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 1000000,
        'approved_at' => null,
    ]);

    expect($tagihan->fresh()->status)->toBe('unpaid');

    Livewire::actingAs($admin)
        ->test(PembayaranShow::class, ['id' => $pembayaran->id])
        ->call('approve');

    expect($tagihan->fresh()->status)->toBe('paid');
});

it('does not mark a tagihan paid via the API when only pending payments cover it', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create(['total' => 1000000, 'status' => 'unpaid']);

    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 900000,
        'approved_at' => null,
    ]);

    $this->actingAs($admin)->postJson('/api/pembayaran', [
        'id_tagihan' => $tagihan->id,
        'nominal' => 100000,
    ])->assertCreated();

    expect($tagihan->fresh()->status)->toBe('unpaid');
});
