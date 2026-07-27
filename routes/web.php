<?php

use App\Http\Controllers\Web\AdminWebLoginController;
use App\Http\Controllers\Web\DosenDashboardController;
use App\Http\Controllers\Web\KrsCetakController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\MahasiswaDashboardController;
use App\Http\Controllers\Web\NilaiExportController;
use App\Http\Controllers\Web\SuperadminEnvConfigController;
use App\Http\Controllers\Web\SuperadminTestUploadController;
use App\Http\Controllers\Web\SuperadminWebLoginController;
use App\Livewire\Admin\Dosen\Form as DosenForm;
use App\Livewire\Admin\Dosen\Index as DosenIndex;
use App\Livewire\Admin\Dosen\Show as DosenShow;
use App\Livewire\Admin\Fakultas\Form as FakultasForm;
use App\Livewire\Admin\Fakultas\Index as FakultasIndex;
use App\Livewire\Admin\Jadwal\Form as JadwalForm;
use App\Livewire\Admin\Jadwal\Index as JadwalIndex;
use App\Livewire\Admin\Jadwal\Show as JadwalShow;
use App\Livewire\Admin\JalurMasuk\Form as JalurMasukForm;
use App\Livewire\Admin\JalurMasuk\Index as JalurMasukIndex;
use App\Livewire\Admin\JenisDaftar\Form as JenisDaftarForm;
use App\Livewire\Admin\JenisDaftar\Index as JenisDaftarIndex;
use App\Livewire\Admin\JenisPenilaian\Form as JenisPenilaianForm;
use App\Livewire\Admin\JenisPenilaian\Index as JenisPenilaianIndex;
use App\Livewire\Admin\Jenjang\Form as JenjangForm;
use App\Livewire\Admin\Jenjang\Index as JenjangIndex;
use App\Livewire\Admin\Kelas\Form as KelasForm;
use App\Livewire\Admin\Kelas\Index as KelasIndex;
use App\Livewire\Admin\Kelas\Show as KelasShow;
use App\Livewire\Admin\KelompokKelas\Form as KelompokKelasForm;
use App\Livewire\Admin\KelompokKelas\Index as KelompokKelasIndex;
use App\Livewire\Admin\KonversiNilai\Form as KonversiNilaiForm;
use App\Livewire\Admin\KonversiNilai\Index as KonversiNilaiIndex;
use App\Livewire\Admin\KonversiNilai\Show as KonversiNilaiShow;
use App\Livewire\Admin\Krs\Form as KrsForm;
use App\Livewire\Admin\Krs\Index as KrsIndex;
use App\Livewire\Admin\Krs\Show as KrsShow;
use App\Livewire\Admin\Kurikulum\Form as KurikulumForm;
use App\Livewire\Admin\Kurikulum\Index as KurikulumIndex;
use App\Livewire\Admin\Kurikulum\Show as KurikulumShow;
use App\Livewire\Admin\Mahasiswa\Form as MahasiswaForm;
use App\Livewire\Admin\Mahasiswa\Index as MahasiswaIndex;
use App\Livewire\Admin\Mahasiswa\Show as MahasiswaShow;
use App\Livewire\Admin\Matkul\Form as MatkulForm;
use App\Livewire\Admin\Matkul\Index as MatkulIndex;
use App\Livewire\Admin\Matkul\Show as MatkulShow;
use App\Livewire\Admin\Nilai\Form as NilaiForm;
use App\Livewire\Admin\Nilai\Index as NilaiIndex;
use App\Livewire\Admin\Nilai\Show as NilaiShow;
use App\Livewire\Admin\Pengguna\Form as PenggunaForm;
use App\Livewire\Admin\Pengguna\Index as PenggunaIndex;
use App\Livewire\Admin\Pengguna\Show as PenggunaShow;
use App\Livewire\Admin\PerguruanTinggi as AdminPerguruanTinggi;
use App\Livewire\Admin\Perkuliahan\Index as PerkuliahanIndex;
use App\Livewire\Admin\Perkuliahan\Show as PerkuliahanShow;
use App\Livewire\Admin\Permission\Form as PermissionForm;
use App\Livewire\Admin\Permission\Index as PermissionIndex;
use App\Livewire\Admin\Prodi\Form as ProdiForm;
use App\Livewire\Admin\Prodi\Index as ProdiIndex;
use App\Livewire\Admin\Profil as AdminProfil;
use App\Livewire\Admin\RentangNilai\Form as RentangNilaiForm;
use App\Livewire\Admin\RentangNilai\Index as RentangNilaiIndex;
use App\Livewire\Admin\Role\Form as RoleForm;
use App\Livewire\Admin\Role\Index as RoleIndex;
use App\Livewire\Admin\Ruangan\Form as RuanganForm;
use App\Livewire\Admin\Ruangan\Index as RuanganIndex;
use App\Livewire\Admin\Semester\Form as SemesterForm;
use App\Livewire\Admin\Semester\Index as SemesterIndex;
use App\Livewire\Admin\StatusAkademik\Form as StatusAkademikForm;
use App\Livewire\Admin\StatusAkademik\Index as StatusAkademikIndex;
use App\Livewire\Dosen\Profil as DosenProfil;
use App\Livewire\Mahasiswa\Profil as MahasiswaProfil;
use Illuminate\Support\Facades\Route;

// Login gabungan — satu pintu masuk untuk semua tipe akun (admin/akademik/keuangan, dosen,
// mahasiswa), user dikenali lewat email atau username. Tujuan redirect ditentukan oleh
// User::webDashboardRouteName().
Route::get('/', [LoginController::class, 'create'])->name('login');
Route::post('/', [LoginController::class, 'store']);

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/dashboard', [SuperadminWebLoginController::class, 'dashboard'])
    ->middleware(['auth', 'superadmin.web'])
    ->name('dashboard');

Route::middleware(['auth', 'superadmin.web'])->group(function (): void {
    Route::get('/konfigurasi', [SuperadminEnvConfigController::class, 'edit'])->name('superadmin.konfigurasi');
    Route::put('/konfigurasi', [SuperadminEnvConfigController::class, 'update'])->name('superadmin.konfigurasi.update');
    Route::view('/migrasi', 'superadmin.migrasi')->name('superadmin.migrasi');
    Route::get('/test-upload', [SuperadminTestUploadController::class, 'create'])->name('superadmin.test-upload');
    Route::post('/test-upload', [SuperadminTestUploadController::class, 'store'])->name('superadmin.test-upload.store');
});

// Dashboard dosen — placeholder, modulnya menyusul.
Route::middleware(['auth', 'role.dosen.web'])->group(function (): void {
    Route::get('/dosen/dashboard', [DosenDashboardController::class, 'index'])->name('dosen.dashboard');
    Route::livewire('/dosen/profil', DosenProfil::class)->name('dosen.profil');
});

// Dashboard mahasiswa — placeholder, modulnya menyusul.
Route::middleware(['auth', 'role.mahasiswa.web'])->group(function (): void {
    Route::get('/mahasiswa/dashboard', [MahasiswaDashboardController::class, 'index'])->name('mahasiswa.dashboard');
    Route::livewire('/mahasiswa/profil', MahasiswaProfil::class)->name('mahasiswa.profil');
});

// Panel admin (Livewire) — superadmin/akademik/keuangan. Login-nya sendiri sudah disatukan
// di atas; grup ini menyisakan logout dan halaman-halaman panel.
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::post('/logout', [AdminWebLoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'role.admin.web'])->group(function (): void {
        Route::get('/dashboard', [AdminWebLoginController::class, 'dashboard'])->name('dashboard');

        // Menu Akademik
        Route::livewire('akademik/matkul', MatkulIndex::class)->name('akademik.matkul');
        Route::livewire('akademik/matkul/create', MatkulForm::class)->name('akademik.matkul.create');
        Route::livewire('akademik/matkul/{id}/edit', MatkulForm::class)->name('akademik.matkul.edit');
        Route::livewire('akademik/matkul/{id}', MatkulShow::class)->name('akademik.matkul.show');

        Route::livewire('akademik/jenis-penilaian', JenisPenilaianIndex::class)->name('akademik.jenis-penilaian');
        Route::livewire('akademik/jenis-penilaian/create', JenisPenilaianForm::class)->name('akademik.jenis-penilaian.create');
        Route::livewire('akademik/jenis-penilaian/{id}/edit', JenisPenilaianForm::class)->name('akademik.jenis-penilaian.edit');

        Route::livewire('akademik/kurikulum', KurikulumIndex::class)->name('akademik.kurikulum');
        Route::livewire('akademik/kurikulum/create', KurikulumForm::class)->name('akademik.kurikulum.create');
        Route::livewire('akademik/kurikulum/{id}/edit', KurikulumForm::class)->name('akademik.kurikulum.edit');
        Route::livewire('akademik/kurikulum/{id}', KurikulumShow::class)->name('akademik.kurikulum.show');

        Route::livewire('akademik/krs', KrsIndex::class)->name('akademik.krs');
        Route::livewire('akademik/krs/create', KrsForm::class)->name('akademik.krs.create');
        Route::livewire('akademik/krs/{id}/edit', KrsForm::class)->name('akademik.krs.edit');
        Route::get('akademik/krs/{id}/cetak', [KrsCetakController::class, 'show'])->name('akademik.krs.cetak');
        Route::livewire('akademik/krs/{id}', KrsShow::class)->name('akademik.krs.show');

        Route::livewire('akademik/nilai', NilaiIndex::class)->name('akademik.nilai');
        Route::get('akademik/nilai/{id}/export', [NilaiExportController::class, 'excel'])->name('akademik.nilai.export');
        Route::get('akademik/nilai/{id}/cetak', [NilaiExportController::class, 'pdf'])->name('akademik.nilai.cetak');
        Route::livewire('akademik/nilai/{id}/{idKrs}/edit', NilaiForm::class)->name('akademik.nilai.edit');
        Route::livewire('akademik/nilai/{id}', NilaiShow::class)->name('akademik.nilai.show');

        Route::livewire('akademik/rentang-nilai', RentangNilaiIndex::class)->name('akademik.rentang-nilai');
        Route::livewire('akademik/rentang-nilai/create', RentangNilaiForm::class)->name('akademik.rentang-nilai.create');
        Route::livewire('akademik/rentang-nilai/{id}/edit', RentangNilaiForm::class)->name('akademik.rentang-nilai.edit');

        Route::livewire('akademik/konversi-nilai', KonversiNilaiIndex::class)->name('akademik.konversi-nilai');
        Route::livewire('akademik/konversi-nilai/create', KonversiNilaiForm::class)->name('akademik.konversi-nilai.create');
        Route::livewire('akademik/konversi-nilai/{id}', KonversiNilaiShow::class)->name('akademik.konversi-nilai.show');

        // Rute literal (create) harus didaftarkan sebelum 'akademik/kelas/{id}' supaya tidak
        // tertangkap sebagai id (lihat catatan di skill siak-livewire-module).
        Route::livewire('akademik/kelas', KelasIndex::class)->name('akademik.kelas');
        Route::livewire('akademik/kelas/create', KelasForm::class)->name('akademik.kelas.create');
        Route::livewire('akademik/kelas/{id}/edit', KelasForm::class)->name('akademik.kelas.edit');
        Route::livewire('akademik/kelas/{id}', KelasShow::class)->name('akademik.kelas.show');

        Route::livewire('akademik/jadwal', JadwalIndex::class)->name('akademik.jadwal');
        Route::livewire('akademik/jadwal/create', JadwalForm::class)->name('akademik.jadwal.create');
        Route::livewire('akademik/jadwal/{id}/edit', JadwalForm::class)->name('akademik.jadwal.edit');
        Route::livewire('akademik/jadwal/{id}', JadwalShow::class)->name('akademik.jadwal.show');

        // Modul monitoring, tanpa create/edit — cermin dari halaman admin/perkuliahan di frontend
        // (daftar kelas + detail sesi & rekap kehadiran per kelas).
        Route::livewire('akademik/perkuliahan', PerkuliahanIndex::class)->name('akademik.perkuliahan');
        Route::livewire('akademik/perkuliahan/{id}', PerkuliahanShow::class)->name('akademik.perkuliahan.show');

        // Menu Administrasi
        Route::livewire('administrasi/dosen', DosenIndex::class)->name('administrasi.dosen');
        Route::livewire('administrasi/dosen/create', DosenForm::class)->name('administrasi.dosen.create');
        Route::livewire('administrasi/dosen/{id}/edit', DosenForm::class)->name('administrasi.dosen.edit');
        Route::livewire('administrasi/dosen/{id}', DosenShow::class)->name('administrasi.dosen.show');

        Route::livewire('administrasi/mahasiswa', MahasiswaIndex::class)->name('administrasi.mahasiswa');
        Route::livewire('administrasi/mahasiswa/{id}/edit', MahasiswaForm::class)->name('administrasi.mahasiswa.edit');
        Route::livewire('administrasi/mahasiswa/{id}', MahasiswaShow::class)->name('administrasi.mahasiswa.show');

        Route::livewire('administrasi/kelas-mahasiswa', KelompokKelasIndex::class)->name('administrasi.kelas-mahasiswa');
        Route::livewire('administrasi/kelas-mahasiswa/create', KelompokKelasForm::class)->name('administrasi.kelas-mahasiswa.create');
        Route::livewire('administrasi/kelas-mahasiswa/{id}/edit', KelompokKelasForm::class)->name('administrasi.kelas-mahasiswa.edit');

        Route::livewire('administrasi/ruangan', RuanganIndex::class)->name('administrasi.ruangan');
        Route::livewire('administrasi/ruangan/create', RuanganForm::class)->name('administrasi.ruangan.create');
        Route::livewire('administrasi/ruangan/{id}/edit', RuanganForm::class)->name('administrasi.ruangan.edit');

        Route::livewire('fakultas', FakultasIndex::class)->name('fakultas.index');
        Route::livewire('fakultas/create', FakultasForm::class)->name('fakultas.create');
        Route::livewire('fakultas/{id}/edit', FakultasForm::class)->name('fakultas.edit');

        Route::livewire('prodi', ProdiIndex::class)->name('prodi.index');
        Route::livewire('prodi/create', ProdiForm::class)->name('prodi.create');
        Route::livewire('prodi/{id}/edit', ProdiForm::class)->name('prodi.edit');

        Route::livewire('perguruan-tinggi', AdminPerguruanTinggi::class)->name('perguruan-tinggi');

        Route::livewire('jenjang', JenjangIndex::class)->name('jenjang.index');
        Route::livewire('jenjang/create', JenjangForm::class)->name('jenjang.create');
        Route::livewire('jenjang/{id}/edit', JenjangForm::class)->name('jenjang.edit');

        Route::livewire('jalur-masuk', JalurMasukIndex::class)->name('jalur-masuk.index');
        Route::livewire('jalur-masuk/create', JalurMasukForm::class)->name('jalur-masuk.create');
        Route::livewire('jalur-masuk/{id}/edit', JalurMasukForm::class)->name('jalur-masuk.edit');

        Route::livewire('semester', SemesterIndex::class)->name('semester.index');
        Route::livewire('semester/create', SemesterForm::class)->name('semester.create');
        Route::livewire('semester/{id}/edit', SemesterForm::class)->name('semester.edit');

        Route::livewire('jenis-daftar', JenisDaftarIndex::class)->name('jenis-daftar.index');
        Route::livewire('jenis-daftar/create', JenisDaftarForm::class)->name('jenis-daftar.create');
        Route::livewire('jenis-daftar/{id}/edit', JenisDaftarForm::class)->name('jenis-daftar.edit');

        Route::livewire('status-akademik', StatusAkademikIndex::class)->name('status-akademik.index');
        Route::livewire('status-akademik/create', StatusAkademikForm::class)->name('status-akademik.create');
        Route::livewire('status-akademik/{id}/edit', StatusAkademikForm::class)->name('status-akademik.edit');

        // Menu Pengguna — rute literal (create/role/permission) harus didaftarkan sebelum
        // 'pengguna/{id}' supaya tidak tertangkap sebagai id (lihat catatan di skill siak-livewire-module).
        Route::livewire('pengguna', PenggunaIndex::class)->name('pengguna.index');
        Route::livewire('pengguna/create', PenggunaForm::class)->name('pengguna.create');

        Route::livewire('pengguna/role', RoleIndex::class)->name('pengguna.role.index');
        Route::livewire('pengguna/role/create', RoleForm::class)->name('pengguna.role.create');
        Route::livewire('pengguna/role/{id}/edit', RoleForm::class)->name('pengguna.role.edit');

        Route::livewire('pengguna/permission', PermissionIndex::class)->name('pengguna.permission.index');
        Route::livewire('pengguna/permission/create', PermissionForm::class)->name('pengguna.permission.create');
        Route::livewire('pengguna/permission/{id}/edit', PermissionForm::class)->name('pengguna.permission.edit');

        Route::livewire('pengguna/{id}/edit', PenggunaForm::class)->name('pengguna.edit');
        Route::livewire('pengguna/{id}', PenggunaShow::class)->name('pengguna.show');

        Route::livewire('profil', AdminProfil::class)->name('profil');
    });
});
