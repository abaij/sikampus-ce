<?php

use App\Support\PanelAccess;

it('lets an akademik admin open akademik and administrasi modules', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.akademik.kurikulum'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa'))->assertOk();
});

it('blocks an akademik admin from keuangan and pengguna modules', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.pengguna.index'))->assertForbidden();
});

it('lets a keuangan admin open keuangan modules only', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))->assertOk();
    $this->actingAs($admin)->get(route('admin.akademik.kurikulum'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa'))->assertForbidden();
});

it('opens a module for a keuangan admin who is granted the permission directly', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo('manage mahasiswa');

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa'))->assertOk();
    // Modul lain yang tidak diberikan tetap tertutup.
    $this->actingAs($admin)->get(route('admin.akademik.kurikulum'))->assertForbidden();
});

it('lets a superadmin open every module', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.akademik.kurikulum'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))->assertOk();
    $this->actingAs($admin)->get(route('admin.pengguna.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.sistem.pengaturan'))->assertOk();
});

it('keeps the dashboard and profil reachable for every admin', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.profil'))->assertOk();
});

it('guards nested routes of a restricted module too', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.akademik.kurikulum.create'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.akademik.kurikulum.edit', ['id' => 1]))->assertForbidden();
});

it('hides menu groups the user has no access to', function () {
    $keuangan = adminUser('admin_keuangan');

    $html = $this->actingAs($keuangan)->get(route('admin.dashboard'))->getContent();

    expect($html)->toContain(route('admin.keuangan.tagihan'));
    expect($html)->not->toContain(route('admin.akademik.kurikulum'));
    expect($html)->not->toContain(route('admin.pengguna.index'));
    expect($html)->not->toContain(route('admin.sistem.pengaturan'));
});

it('shows a menu item unlocked by a direct permission', function () {
    $keuangan = adminUser('admin_keuangan');
    $keuangan->givePermissionTo('manage mahasiswa');

    $html = $this->actingAs($keuangan)->get(route('admin.dashboard'))->getContent();

    expect($html)->toContain(route('admin.administrasi.mahasiswa'));
    expect($html)->not->toContain(route('admin.administrasi.dosen'));
});

it('resolves the required permission from the longest matching route prefix', function () {
    expect(PanelAccess::permissionFor('admin.pengguna.role.create'))->toBe('manage role');
    expect(PanelAccess::permissionFor('admin.pengguna.index'))->toBe('manage pengguna');
    expect(PanelAccess::permissionFor('admin.akademik.jadwal-ujian'))->toBe('manage jadwal ujian');
    expect(PanelAccess::permissionFor('admin.akademik.jadwal.edit'))->toBe('manage jadwal');
    expect(PanelAccess::permissionFor('admin.dashboard'))->toBeNull();
    expect(PanelAccess::permissionFor('dosen.dashboard'))->toBeNull();
});
