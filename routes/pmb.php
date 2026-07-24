<?php

use App\Http\Controllers\Pmb\AdminDashboardController;
use App\Http\Controllers\Pmb\AuthController;
use App\Http\Controllers\Pmb\BiayaController;
use App\Http\Controllers\Pmb\CamabaController;
use App\Http\Controllers\Pmb\DaftarUlangController;
use App\Http\Controllers\Pmb\DataAcuanController;
use App\Http\Controllers\Pmb\DokumenController;
use App\Http\Controllers\Pmb\FaqController;
use App\Http\Controllers\Pmb\JalurMasukController;
use App\Http\Controllers\Pmb\JenisDaftarController;
use App\Http\Controllers\Pmb\JenisTesController;
use App\Http\Controllers\Pmb\PaymentInfoController;
use App\Http\Controllers\Pmb\PembayaranController;
use App\Http\Controllers\Pmb\PendaftaranController;
use App\Http\Controllers\Pmb\PendaftaranImportController;
use App\Http\Controllers\Pmb\PendaftarController;
use App\Http\Controllers\Pmb\PenggunaController;
use App\Http\Controllers\Pmb\PeriodeController;
use App\Http\Controllers\Pmb\PersyaratanController;
use App\Http\Controllers\Pmb\ProdiController;
use App\Http\Controllers\Pmb\RegisterController;
use App\Http\Controllers\Pmb\SeleksiInputController;
use App\Models\PmbFaq;
use Illuminate\Support\Facades\Route;

Route::bind('faq', function (string $value) {
    return PmbFaq::where('id', $value)->firstOrFail();
});

Route::prefix('pmb')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('admin/login', [AuthController::class, 'adminLogin']);

    // Route pendaftaran multi-step (publik)
    Route::get('register/periode-aktif', [RegisterController::class, 'getPeriodeAktif']);
    Route::get('register/faq', [RegisterController::class, 'getFaqPeriodeAktif']);
    Route::post('register/step1', [RegisterController::class, 'step1']);
    Route::post('register/step2', [RegisterController::class, 'step2']);
    Route::post('register/step3', [RegisterController::class, 'step3']);
    Route::post('register/step4', [RegisterController::class, 'step4']);
    Route::post('register/step5', [RegisterController::class, 'step5']);

    // Route untuk mendapatkan data master (prodi, jalur masuk, jenis daftar)
    Route::get('prodi', [ProdiController::class, 'index']);
    Route::get('jalur-masuk', [JalurMasukController::class, 'index']);
    Route::get('jenis-daftar', [JenisDaftarController::class, 'index']);

    Route::get('payment-info', [PaymentInfoController::class, 'show']);

    // Route untuk mendapatkan data pendaftaran yang sudah ada (perlu auth)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('register/pendaftaran-data', [RegisterController::class, 'getPendaftaranData']);
        Route::post('register/biodata', [RegisterController::class, 'updateMyBiodata']);
        Route::post('register/daftar-ulang-bukti', [RegisterController::class, 'uploadDaftarUlangBukti']);
        Route::post('register/dokumen/{dokumen}/reupload', [RegisterController::class, 'reuploadDokumen']);
    });

    Route::get('test', function () {
        return response()->json([
            'message' => 'Hello World',
        ]);
    });

    // Routes yang memerlukan autentikasi admin
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('admin/me', [AuthController::class, 'adminMe']);
        Route::put('admin/me', [AuthController::class, 'adminUpdateProfile']);
        Route::put('admin/me/password', [AuthController::class, 'adminUpdatePassword']);
        Route::get('admin/dashboard-stats', [AdminDashboardController::class, 'stats']);
        Route::get('admin/dashboard-chart-tahunan', [AdminDashboardController::class, 'chartYearly5Years']);

        // Data acuan untuk form prodi (fakultas, jenjang, dosen)
        Route::get('data-acuan/fakultas', [DataAcuanController::class, 'fakultas']);
        Route::get('data-acuan/jenjang', [DataAcuanController::class, 'jenjang']);
        Route::get('data-acuan/dosen', [DataAcuanController::class, 'dosen']);

        // CRUD Prodi (data acuan) - GET prodi is public for registration; store/update/destroy require auth
        Route::apiResource('prodi', ProdiController::class)->except(['index']);

        // CRUD Jalur Masuk - GET jalur-masuk is public for registration; store/update/destroy require auth
        Route::apiResource('jalur-masuk', JalurMasukController::class)->except(['index']);

        // CRUD Jenis Daftar - GET jenis-daftar is public for registration; store/update/destroy require auth
        Route::apiResource('jenis-daftar', JenisDaftarController::class)->except(['index']);

        // CRUD Periode
        Route::apiResource('periode', PeriodeController::class);

        // CRUD Persyaratan
        Route::apiResource('persyaratan', PersyaratanController::class);

        // CRUD Biaya
        Route::apiResource('biaya', BiayaController::class);

        // CRUD FAQ (pmb_faq)
        Route::apiResource('faq', FaqController::class);

        Route::put('payment-info', [PaymentInfoController::class, 'update']);

        // CRUD Jenis tes masuk (pmb_jenis_tes)
        Route::apiResource('jenis-tes', JenisTesController::class);

        // CRUD Pengguna
        Route::apiResource('pengguna', PenggunaController::class);
        Route::post('pengguna/{pengguna}/reset-password', [PenggunaController::class, 'resetPassword']);
        Route::get('pengguna/template/download', [PenggunaController::class, 'downloadTemplate']);
        Route::post('pengguna/import', [PenggunaController::class, 'import']);

        // Akun camaba (semua baris `pmb_camaba` + status pendaftaran per periode)
        Route::get('camaba', [CamabaController::class, 'index']);
        Route::get('camaba/{camaba}', [CamabaController::class, 'show']);
        Route::post('camaba/{camaba}/kirim-email-kontak', [CamabaController::class, 'kirimEmailKontak']);
        Route::delete('camaba/{camaba}', [CamabaController::class, 'destroy']);

        // Data Pendaftar (`export` harus sebelum `{pendaftar}`)
        Route::get('pendaftar/export', [PendaftarController::class, 'export']);
        Route::get('pendaftar', [PendaftarController::class, 'index']);
        Route::get('pendaftar/{pendaftar}', [PendaftarController::class, 'show']);
        Route::put('pendaftar/{pendaftar}', [PendaftarController::class, 'update']);

        // Data Pendaftaran
        Route::get('pendaftaran', [PendaftaranController::class, 'index']);
        Route::get('pendaftaran/seleksi/export', [PendaftaranController::class, 'exportSeleksi']);
        Route::get('pendaftaran/seleksi', [PendaftaranController::class, 'seleksi']);
        Route::patch('pendaftaran/{pendaftaran}/status', [PendaftaranController::class, 'updateStatus']);
        Route::get('pendaftaran/{pendaftaran}/seleksi-input', [SeleksiInputController::class, 'show']);
        Route::put('pendaftaran/{pendaftaran}/seleksi-input', [SeleksiInputController::class, 'update']);
        Route::get('pendaftaran/{pendaftaran}', [PendaftaranController::class, 'show']);
        Route::get('pendaftaran/template/download', [PendaftaranImportController::class, 'downloadTemplate']);
        Route::post('pendaftaran/import', [PendaftaranImportController::class, 'import']);

        // Verifikasi Pembayaran
        Route::get('pembayaran', [PembayaranController::class, 'index']);
        Route::get('pembayaran/{pembayaran}', [PembayaranController::class, 'show']);
        Route::post('pembayaran/{pembayaran}/verify', [PembayaranController::class, 'verify']);

        // Verifikasi dokumen PMB
        Route::patch('dokumen/{dokumen}/status', [DokumenController::class, 'updateStatus']);

        // CRUD Daftar ulang (pmb_daftar_ulang)
        Route::get('daftar-ulang', [DaftarUlangController::class, 'index']);
        Route::get('daftar-ulang/last-nim', [DaftarUlangController::class, 'lastNim']);
        Route::post('daftar-ulang', [DaftarUlangController::class, 'store']);
        Route::post('daftar-ulang/{pmb_daftar_ulang}/transfer-ke-mahasiswa', [DaftarUlangController::class, 'transferToMahasiswa']);
        Route::get('daftar-ulang/{pmb_daftar_ulang}', [DaftarUlangController::class, 'show']);
        Route::put('daftar-ulang/{pmb_daftar_ulang}', [DaftarUlangController::class, 'update']);
        Route::delete('daftar-ulang/{pmb_daftar_ulang}', [DaftarUlangController::class, 'destroy']);
        Route::post('daftar-ulang/{pmb_daftar_ulang}/kirim-email-selesai', [DaftarUlangController::class, 'kirimEmailSelesai']);
    });
});
