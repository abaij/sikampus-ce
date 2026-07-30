<?php

/*
|--------------------------------------------------------------------------
| Hak akses panel admin per menu/rute
|--------------------------------------------------------------------------
|
| Peta nama-rute → permission Spatie yang dibutuhkan. Satu peta ini dipakai
| oleh DUA tempat sekaligus supaya tampilan dan fungsi tidak pernah berbeda:
|
|  - App\Http\Middleware\EnsurePanelPermission (menjaga rutenya, 403)
|  - App\Support\PanelAccess::allows()          (menyembunyikan menu di navbar)
|
| Kuncinya adalah PREFIX nama rute (tanpa awalan "admin."); yang paling
| panjang menang, jadi 'pengguna.role' menutup 'pengguna.role.index' dan
| 'pengguna.role.create' sekaligus tanpa mengganggu 'pengguna.index'.
|
| Rute yang tidak ada di peta ini bebas diakses semua admin (mis. dashboard
| dan profil). Karena pengecekan memakai $user->can(), permission yang
| diberikan langsung ke seorang user (model_has_permissions) otomatis
| membuka akses walau role-nya sendiri tidak punya — persis kebutuhan
| "admin keuangan yang diberi permission mahasiswa boleh membuka mahasiswa".
|
*/

return [
    'route_permissions' => [
        // Akademik
        'akademik.matkul' => 'manage mata kuliah',
        'akademik.jenis-penilaian' => 'manage jenis penilaian',
        'akademik.kurikulum' => 'manage kurikulum',
        'akademik.krs' => 'manage krs',
        'akademik.nilai' => 'manage nilai',
        'akademik.rentang-nilai' => 'manage rentang nilai',
        'akademik.konversi-nilai' => 'manage konversi nilai',
        'akademik.kelas' => 'manage kelas',
        'akademik.jadwal' => 'manage jadwal',
        'akademik.jadwal-ujian' => 'manage jadwal ujian',
        'akademik.perkuliahan' => 'manage perkuliahan',
        'akademik.tugas-akhir' => 'manage tugas akhir',
        'akademik.yudisium' => 'manage yudisium',
        'akademik.wisuda' => 'manage wisuda',

        // Administrasi
        'administrasi.dosen' => 'manage dosen',
        'administrasi.dosen-wali' => 'manage dosen wali',
        'administrasi.mahasiswa' => 'manage mahasiswa',
        'administrasi.kelas-mahasiswa' => 'manage grup mahasiswa',
        'administrasi.ruangan' => 'manage ruangan',
        'administrasi.survey' => 'manage survey',
        'administrasi.pengumuman' => 'manage pengumuman',
        'fakultas' => 'manage fakultas',
        'prodi' => 'manage prodi',
        'perguruan-tinggi' => 'manage perguruan tinggi',
        'jenjang' => 'manage jenjang',

        // Keuangan
        'keuangan.tagihan' => 'manage tagihan',
        'keuangan.pembayaran' => 'manage pembayaran',
        'keuangan.keringanan-biaya' => 'manage keringanan biaya',
        'keuangan.jenis-keringanan-biaya' => 'manage jenis keringanan biaya',
        'keuangan.aturan-akses-keuangan' => 'manage aturan akses keuangan',
        'keuangan.struktur-biaya' => 'manage struktur biaya',
        'keuangan.komponen-biaya' => 'manage komponen biaya',
        'keuangan.kategori-biaya' => 'manage kategori biaya',

        // Pengaturan akademik — permission-nya milik grup akademik/administrasi,
        // jadi admin akademik tetap bisa mengelolanya walau menunya ada di bawah
        // "Pengaturan". Pindahkan permission-nya di PermissionSeeder kalau ingin
        // menu ini benar-benar superadmin-only.
        'semester' => 'manage semester',
        'jalur-masuk' => 'manage jalur masuk',
        'jenis-daftar' => 'manage jenis pendaftaran',
        'status-akademik' => 'manage status akademik',

        // Pengaturan pengguna — permission-nya hanya dimiliki Superadmin.
        'pengguna.role' => 'manage role',
        'pengguna.permission' => 'manage permission',
        'pengguna' => 'manage pengguna',

        // Modul sistem tetap dijaga ganda oleh middleware role.admin.superadmin di routes/web.php;
        // entri ini yang membuat menunya ikut hilang dari navbar untuk non-superadmin.
        'sistem' => 'manage sistem',
    ],
];
