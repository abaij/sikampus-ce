<?php

namespace Database\Seeders;

use App\Models\Role as AppRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Buat permissions untuk menu akademik
        $akademikPermissions = [
            'view akademik',
            'manage semester',
            'manage kurikulum',
            'manage mata kuliah',
            'manage kelas',
            'manage jadwal',
            'manage jadwal ujian',
            'manage krs',
            'manage perkuliahan',
            'manage nilai',
            'manage rentang nilai',
            'manage konversi nilai',
            'manage tugas akhir',
            'manage yudisium',
            'manage wisuda',
        ];

        // Buat permissions untuk menu keuangan
        $keuanganPermissions = [
            'view keuangan',
            'manage tagihan',
            'manage pembayaran',
            'manage struktur biaya',
            'manage komponen biaya',
            'manage kategori biaya',
            'manage kebijakan biaya',
            'manage jenis keringanan biaya',
            'manage keringanan biaya',
            'manage aturan akses keuangan',
        ];

        // Buat permissions untuk menu administrasi
        $administrasiPermissions = [
            'view administrasi',
            'manage mahasiswa',
            'manage grup mahasiswa',
            'manage dosen',
            'manage dosen wali',
            'manage fakultas',
            'manage prodi',
            'manage perguruan tinggi',
            'manage jenis mata kuliah',
            'manage jenis penilaian',
            'manage jenjang',
            'manage jalur masuk',
            'manage jenis pendaftaran',
            'manage status akademik',
            'manage ruangan',
            'manage survey',
            'manage pengumuman',
        ];

        // Buat permissions untuk menu laporan
        $laporanPermissions = [
            'view laporan',
            'view laporan mahasiswa aktif',
            'view laporan krs',
            'view laporan nilai',
        ];

        // Buat permissions untuk menu pengaturan
        $pengaturanPermissions = [
            'view pengaturan',
            'manage pengguna',
            'manage role',
            'manage permission',
            'manage sistem',
        ];

        $allPermissions = array_merge(
            $akademikPermissions,
            $keuanganPermissions,
            $administrasiPermissions,
            $laporanPermissions,
            $pengaturanPermissions
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Buat atau update roles
        $superadminRole = AppRole::firstOrCreate(
            ['name' => 'Superadmin', 'guard_name' => 'web'],
            ['code' => 'superadmin']
        );
        $superadminRole->syncPermissions($allPermissions);

        $akademikRole = AppRole::firstOrCreate(
            ['name' => 'Akademik', 'guard_name' => 'web'],
            ['code' => 'akademik']
        );
        // Admin akademik: menu Akademik + Administrasi (+ laporan). Tidak menyentuh keuangan
        // maupun pengaturan pengguna.
        $akademikRole->syncPermissions(array_merge(
            $akademikPermissions,
            $administrasiPermissions,
            $laporanPermissions
        ));

        $keuanganRole = AppRole::firstOrCreate(
            ['name' => 'Keuangan', 'guard_name' => 'web'],
            ['code' => 'keuangan']
        );
        // Admin keuangan: menu Keuangan saja. Kalau seorang staf keuangan tetap perlu membuka
        // modul lain (mis. data mahasiswa), berikan permission-nya langsung ke user
        // bersangkutan lewat tab Permission di halaman Pengguna — pengecekan memakai
        // $user->can() sehingga permission langsung otomatis memperluas aksesnya.
        $keuanganRole->syncPermissions($keuanganPermissions);
    }
}
