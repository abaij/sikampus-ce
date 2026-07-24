<?php

use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;

it('approve pembayaran yang menutupi seluruh tagihan mengubah status tagihan jadi paid dan mengirim notifikasi', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $mahasiswaUser->id]);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000, 'status' => 'unpaid']);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 500000, 'approved_at' => null]);

    $response = $this->actingAs($admin)->postJson("/api/pembayaran/{$pembayaran->id}/approve");

    $response->assertOk();
    expect(Pembayaran::find($pembayaran->id)->approved_at)->not->toBeNull();
    expect(Tagihan::find($tagihan->id)->status)->toBe('paid');

    $notif = Notifikasi::where('id_user', $mahasiswaUser->id)->where('tipe', 'pembayaran_acc')->first();
    expect($notif)->not->toBeNull();
});

it('approve pembayaran parsial tidak mengubah status tagihan jadi paid', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'status' => 'unpaid']);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 300000, 'approved_at' => null]);

    $this->actingAs($admin)->postJson("/api/pembayaran/{$pembayaran->id}/approve")->assertOk();

    expect(Tagihan::find($tagihan->id)->status)->toBe('unpaid');
});

it('menolak approve ulang pembayaran yang sudah disetujui sebelumnya', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id, 'nominal' => 500000, 'approved_at' => now(), 'approved_by' => 'admin lain',
    ]);

    $response = $this->actingAs($admin)->postJson("/api/pembayaran/{$pembayaran->id}/approve");

    $response->assertStatus(422)->assertJson(['message' => 'Pembayaran sudah disetujui sebelumnya.']);
});

it('menolak approve pembayaran oleh user yang bukan admin keuangan', function () {
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create();
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id]);

    $this->actingAs($mahasiswaUser)
        ->postJson("/api/pembayaran/{$pembayaran->id}/approve")
        ->assertForbidden();

    expect(Pembayaran::find($pembayaran->id)->approved_at)->toBeNull();
});

it('approve pembayaran tetap sukses walau mahasiswa belum punya akun login (id_user kosong)', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => null]);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000]);
    $pembayaran = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 500000, 'approved_at' => null]);

    $this->actingAs($admin)
        ->postJson("/api/pembayaran/{$pembayaran->id}/approve")
        ->assertOk();

    expect(Pembayaran::find($pembayaran->id)->approved_at)->not->toBeNull();
    expect(Notifikasi::count())->toBe(0);
});
