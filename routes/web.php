<?php

use App\Http\Controllers\Web\AdminWebLoginController;
use App\Http\Controllers\Web\SuperadminEnvConfigController;
use App\Http\Controllers\Web\SuperadminTestUploadController;
use App\Http\Controllers\Web\SuperadminWebLoginController;
use App\Livewire\Admin\Dosen\Form as DosenForm;
use App\Livewire\Admin\Dosen\Index as DosenIndex;
use App\Livewire\Admin\Dosen\Show as DosenShow;
use App\Livewire\Admin\Fakultas\Form as FakultasForm;
use App\Livewire\Admin\Fakultas\Index as FakultasIndex;
use App\Livewire\Admin\JalurMasuk\Form as JalurMasukForm;
use App\Livewire\Admin\JalurMasuk\Index as JalurMasukIndex;
use App\Livewire\Admin\Jenjang\Form as JenjangForm;
use App\Livewire\Admin\Jenjang\Index as JenjangIndex;
use App\Livewire\Admin\KelompokKelas\Form as KelompokKelasForm;
use App\Livewire\Admin\KelompokKelas\Index as KelompokKelasIndex;
use App\Livewire\Admin\Mahasiswa\Index as MahasiswaIndex;
use App\Livewire\Admin\Prodi\Form as ProdiForm;
use App\Livewire\Admin\Prodi\Index as ProdiIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SuperadminWebLoginController::class, 'create'])->name('login');
    Route::post('/login', [SuperadminWebLoginController::class, 'store']);
});

Route::post('/logout', [SuperadminWebLoginController::class, 'destroy'])
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

// Panel admin (Livewire) — superadmin/akademik/keuangan, terpisah dari login superadmin di atas.
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AdminWebLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminWebLoginController::class, 'store']);
    });

    Route::post('/logout', [AdminWebLoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'role.admin.web'])->group(function (): void {
        Route::get('/dashboard', [AdminWebLoginController::class, 'dashboard'])->name('dashboard');

        // Menu Akademik — link dummy dulu, modulnya menyusul.
        Route::view('akademik/matkul', 'admin.coming-soon', ['title' => 'Mata Kuliah'])->name('akademik.matkul');
        Route::view('akademik/kurikulum', 'admin.coming-soon', ['title' => 'Kurikulum'])->name('akademik.kurikulum');
        Route::view('akademik/krs', 'admin.coming-soon', ['title' => 'KRS'])->name('akademik.krs');
        Route::view('akademik/nilai', 'admin.coming-soon', ['title' => 'Nilai'])->name('akademik.nilai');

        // Menu Administrasi
        Route::livewire('administrasi/dosen', DosenIndex::class)->name('administrasi.dosen');
        Route::livewire('administrasi/dosen/create', DosenForm::class)->name('administrasi.dosen.create');
        Route::livewire('administrasi/dosen/{id}/edit', DosenForm::class)->name('administrasi.dosen.edit');
        Route::livewire('administrasi/dosen/{id}', DosenShow::class)->name('administrasi.dosen.show');

        Route::livewire('administrasi/mahasiswa', MahasiswaIndex::class)->name('administrasi.mahasiswa');

        Route::livewire('administrasi/kelas-mahasiswa', KelompokKelasIndex::class)->name('administrasi.kelas-mahasiswa');
        Route::livewire('administrasi/kelas-mahasiswa/create', KelompokKelasForm::class)->name('administrasi.kelas-mahasiswa.create');
        Route::livewire('administrasi/kelas-mahasiswa/{id}/edit', KelompokKelasForm::class)->name('administrasi.kelas-mahasiswa.edit');

        Route::livewire('fakultas', FakultasIndex::class)->name('fakultas.index');
        Route::livewire('fakultas/create', FakultasForm::class)->name('fakultas.create');
        Route::livewire('fakultas/{id}/edit', FakultasForm::class)->name('fakultas.edit');

        Route::livewire('prodi', ProdiIndex::class)->name('prodi.index');
        Route::livewire('prodi/create', ProdiForm::class)->name('prodi.create');
        Route::livewire('prodi/{id}/edit', ProdiForm::class)->name('prodi.edit');

        Route::livewire('jenjang', JenjangIndex::class)->name('jenjang.index');
        Route::livewire('jenjang/create', JenjangForm::class)->name('jenjang.create');
        Route::livewire('jenjang/{id}/edit', JenjangForm::class)->name('jenjang.edit');

        Route::livewire('jalur-masuk', JalurMasukIndex::class)->name('jalur-masuk.index');
        Route::livewire('jalur-masuk/create', JalurMasukForm::class)->name('jalur-masuk.create');
        Route::livewire('jalur-masuk/{id}/edit', JalurMasukForm::class)->name('jalur-masuk.edit');
    });
});
