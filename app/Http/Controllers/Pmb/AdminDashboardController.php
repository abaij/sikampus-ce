<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbDaftarUlang;
use App\Models\PmbPendaftaran;
use App\Models\PmbPeriode;
use App\Models\PmbUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Ringkasan statistik dashboard admin PMB untuk periode aktif.
     *
     * - total_pendaftar: jumlah baris pendaftaran status `acc` pada periode aktif.
     * - total_pendaftar_pending: jumlah baris pendaftaran status `pending` pada periode aktif.
     * - camaba_daftar_ulang_acc: jumlah camaba berbeda yang punya baris daftar ulang status `acc` untuk pendaftaran pada periode aktif.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PmbUser || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $periode = PmbPeriode::query()->where('is_active', true)->first();

        if (! $periode) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id_periode' => null,
                    'nama_periode' => null,
                    'total_pendaftar' => 0,
                    'total_pendaftar_pending' => 0,
                    'camaba_daftar_ulang_acc' => 0,
                ],
            ]);
        }

        $periodeId = $periode->id;

        $totalPendaftarCamabaAcc = (int) PmbPendaftaran::query()
            ->where('id_periode', $periodeId)
            ->where('status', 'acc')
            ->count();
        $totalPendaftarCamabaPending = (int) PmbPendaftaran::query()
            ->where('id_periode', $periodeId)
            ->where('status', 'pending')
            ->count();

        $camabaDaftarUlangAcc = (int) PmbDaftarUlang::query()
            ->join('pmb_pendaftaran', 'pmb_pendaftaran.id', '=', 'pmb_daftar_ulang.id_pendaftaran')
            ->where('pmb_pendaftaran.id_periode', $periodeId)
            ->where('pmb_daftar_ulang.status', 'acc')
            ->selectRaw('count(distinct pmb_pendaftaran.id_camaba) as agg')
            ->value('agg');

        return response()->json([
            'success' => true,
            'data' => [
                'id_periode' => $periode->id,
                'nama_periode' => $periode->nama,
                'total_pendaftar' => $totalPendaftarCamabaAcc,
                'total_pendaftar_pending' => $totalPendaftarCamabaPending,
                'camaba_daftar_ulang_acc' => $camabaDaftarUlangAcc,
            ],
        ]);
    }

    /**
     * Data grafik kurva 5 tahun terakhir (kalender):
     * - pendaftaran_acc: jumlah baris pendaftaran status `acc` (tahun dari tanggal_pendaftaran, fallback created_at).
     * - pendaftaran_pending: jumlah baris pendaftaran status `pending` (tahun dari tanggal_pendaftaran, fallback created_at).
     * - daftar_ulang_acc: jumlah camaba berbeda dengan baris daftar ulang status `acc` (tahun dari tanggal_daftar_ulang, fallback updated_at).
     */
    public function chartYearly5Years(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PmbUser || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $endYear = (int) now()->year;
        $startYear = $endYear - 4;

        $series = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $pendaftaranAcc = (int) PmbPendaftaran::query()
                ->where('status', 'acc')
                ->whereRaw('YEAR(COALESCE(tanggal_pendaftaran, created_at)) = ?', [$year])
                ->count();
            $pendaftaranPending = (int) PmbPendaftaran::query()
                ->where('status', 'pending')
                ->whereRaw('YEAR(COALESCE(tanggal_pendaftaran, created_at)) = ?', [$year])
                ->count();

            $daftarUlangAcc = (int) PmbDaftarUlang::query()
                ->join('pmb_pendaftaran', 'pmb_pendaftaran.id', '=', 'pmb_daftar_ulang.id_pendaftaran')
                ->where('pmb_daftar_ulang.status', 'acc')
                ->whereRaw('YEAR(COALESCE(pmb_daftar_ulang.tanggal_daftar_ulang, pmb_daftar_ulang.updated_at)) = ?', [$year])
                ->selectRaw('count(distinct pmb_pendaftaran.id_camaba) as agg')
                ->value('agg');

            $series[] = [
                'tahun' => $year,
                'pendaftaran_acc' => $pendaftaranAcc,
                'pendaftaran_pending' => $pendaftaranPending,
                'daftar_ulang_acc' => $daftarUlangAcc,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'series' => $series,
            ],
        ]);
    }
}
