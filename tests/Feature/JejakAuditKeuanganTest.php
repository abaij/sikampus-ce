<?php

use App\Livewire\Admin\Pembayaran\Form as PembayaranForm;
use App\Livewire\Admin\Pembayaran\Show as PembayaranShow;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PelakuAksi;
use Livewire\Livewire;

it('prefers username, then email, then a stable user id — never the display name', function () {
    $punyaUsername = User::factory()->create(['username' => 'keu01', 'email' => 'keu@example.test', 'name' => 'Budi']);
    expect(PelakuAksi::untukUser($punyaUsername))->toBe('keu01');

    $hanyaEmail = User::factory()->create(['username' => null, 'email' => 'tanpa@example.test', 'name' => 'Budi']);
    expect(PelakuAksi::untukUser($hanyaEmail))->toBe('tanpa@example.test');

    $tanpaKeduanya = User::factory()->create(['username' => '', 'email' => '', 'name' => 'Budi']);
    expect(PelakuAksi::untukUser($tanpaKeduanya))->toBe('user#'.$tanpaKeduanya->id);
});

it('falls back to sistem when nobody is logged in', function () {
    expect(PelakuAksi::sekarang())->toBe(PelakuAksi::SISTEM);

    $tagihan = Tagihan::factory()->create();
    expect($tagihan->created_by)->toBe(PelakuAksi::SISTEM);
});

it('records who created a document without the caller having to remember', function () {
    $admin = adminUser('admin_keuangan');
    $this->actingAs($admin);

    $tagihan = Tagihan::factory()->create();

    expect($tagihan->created_by)->toBe(PelakuAksi::untukUser($admin));
    expect($tagihan->created_by)->not->toBe($admin->name);
});

/**
 * Inti temuan 2.9: kolom updated_by dan deleted_by ada di skema dan di $fillable, tapi tidak ada
 * satu pun jalur yang pernah mengisinya — nol baris terisi di seluruh tabel keuangan.
 */
it('records who last updated a document', function () {
    $tagihan = Tagihan::factory()->create();
    expect($tagihan->updated_by)->toBeNull();

    $admin = adminUser('admin_keuangan');
    $this->actingAs($admin);

    $tagihan->update(['keterangan' => 'dikoreksi']);

    expect($tagihan->fresh()->updated_by)->toBe(PelakuAksi::untukUser($admin));
});

it('records who soft-deleted a document', function () {
    $tagihan = Tagihan::factory()->create();

    $admin = adminUser('admin_keuangan');
    $this->actingAs($admin);

    $tagihan->delete();

    $terhapus = Tagihan::withTrashed()->find($tagihan->id);
    expect($terhapus->deleted_by)->toBe(PelakuAksi::untukUser($admin));
    expect($terhapus->trashed())->toBeTrue();
});

it('applies the audit trail to pembayaran and keringanan biaya too', function () {
    $admin = adminUser('admin_keuangan');
    $this->actingAs($admin);
    $pelaku = PelakuAksi::untukUser($admin);

    $pembayaran = Pembayaran::factory()->create();
    expect($pembayaran->created_by)->toBe($pelaku);

    $pembayaran->update(['keterangan' => 'koreksi']);
    expect($pembayaran->fresh()->updated_by)->toBe($pelaku);

    $pembayaran->delete();
    expect(Pembayaran::withTrashed()->find($pembayaran->id)->deleted_by)->toBe($pelaku);
});

it('does not overwrite a created_by the caller set on purpose', function () {
    $admin = adminUser('admin_keuangan');
    $this->actingAs($admin);

    $tagihan = Tagihan::factory()->create(['created_by' => 'migrasi-lama']);

    expect($tagihan->created_by)->toBe('migrasi-lama');
});

it('stamps the approver with the stable identifier when a payment is approved', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 1000000,
        'approved_at' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(PembayaranShow::class, ['id' => $pembayaran->id])
        ->call('approve');

    expect($pembayaran->fresh()->approved_by)->toBe(PelakuAksi::untukUser($admin));
    expect($pembayaran->fresh()->approved_by)->not->toBe($admin->name);
});

/**
 * Temuan 2.9 bagian ketiga: nominal pembayaran yang sudah disetujui bisa diubah tanpa
 * persetujuan ulang, sehingga satu orang bisa menggeser jumlah uang yang sudah diverifikasi.
 */
it('revokes the approval when an approved payment changes amount', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 400000,
        'approved_at' => now(),
        'approved_by' => 'petugas-lama',
    ]);

    Livewire::actingAs($admin)
        ->test(PembayaranForm::class, ['id' => $pembayaran->id])
        ->set('nominal', '600000')
        ->call('save')
        ->assertHasNoErrors();

    $segar = $pembayaran->fresh();
    expect((float) $segar->nominal)->toBe(600000.0);
    expect($segar->approved_at)->toBeNull();
    expect($segar->approved_by)->toBeNull();
    expect($segar->updated_by)->toBe(PelakuAksi::untukUser($admin));
});

it('keeps the approval when an approved payment is edited without changing the amount', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 400000,
        'approved_at' => now(),
        'approved_by' => 'petugas-lama',
    ]);

    Livewire::actingAs($admin)
        ->test(PembayaranForm::class, ['id' => $pembayaran->id])
        ->set('keterangan', 'transfer BSI')
        ->call('save')
        ->assertHasNoErrors();

    $segar = $pembayaran->fresh();
    expect($segar->approved_at)->not->toBeNull();
    expect($segar->approved_by)->toBe('petugas-lama');
});

it('revokes the approval through the API as well', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 400000,
        'approved_at' => now(),
        'approved_by' => 'petugas-lama',
    ]);

    $this->actingAs($admin)
        ->putJson('/api/pembayaran/'.$pembayaran->id, ['nominal' => 500000])
        ->assertOk()
        ->assertJson(['persetujuan_direset' => true]);

    expect($pembayaran->fresh()->approved_at)->toBeNull();
});

it('leaves the tagihan no longer settled once the approval is revoked', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 1000000]);
    $pembayaran = Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 1000000,
        'approved_at' => now(),
    ]);
    $tagihan->update(['status' => 'paid']);

    Livewire::actingAs($admin)
        ->test(PembayaranForm::class, ['id' => $pembayaran->id])
        ->set('nominal', '900000')
        ->call('save');

    expect($tagihan->fresh()->lunasMenurutPembayaranDisetujui())->toBeFalse();
    expect($tagihan->fresh()->status)->toBe('unpaid');
});
