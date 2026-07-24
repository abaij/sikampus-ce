<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranController extends Controller
{
    /**
     * Menampilkan daftar pembayaran dengan filter status pending.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status', 'pending'); // Default filter pending
        $idPeriode = $request->get('id_periode');

        $query = PmbPembayaran::with([
            'pendaftaran.camaba',
            'pendaftaran.periode',
            'biaya',
        ]);

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by periode
        if ($idPeriode) {
            $query->whereHas('pendaftaran', function ($pendaftaranQuery) use ($idPeriode) {
                $pendaftaranQuery->where('id_periode', $idPeriode);
            });
        }

        // Search by no_kuitansi, nama camaba, atau no_pendaftaran
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_kuitansi', 'like', "%{$search}%")
                  ->orWhereHas('pendaftaran', function ($pendaftaranQuery) use ($search) {
                      $pendaftaranQuery->where('no_pendaftaran', 'like', "%{$search}%")
                                       ->orWhereHas('camaba', function ($camabaQuery) use ($search) {
                                           $camabaQuery->where('nama', 'like', "%{$search}%")
                                                      ->orWhere('email', 'like', "%{$search}%");
                                       });
                  });
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Menampilkan detail pembayaran.
     */
    public function show(PmbPembayaran $pembayaran): JsonResponse
    {
        $pembayaran->load([
            'pendaftaran.camaba',
            'pendaftaran.periode',
            'pendaftaran.jalurMasuk',
            'pendaftaran.jenisDaftar',
            'biaya',
        ]);

        return response()->json([
            'success' => true,
            'data' => $pembayaran,
        ]);
    }

    /**
     * Verifikasi pembayaran (update status menjadi paid/success).
     */
    public function verify(Request $request, PmbPembayaran $pembayaran): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:paid,success,failed,rejected',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $pembayaran->update([
            'status' => $validated['status'],
            'keterangan' => $validated['keterangan'] ?? $pembayaran->keterangan,
        ]);

        $pembayaran->load([
            'pendaftaran.camaba',
            'pendaftaran.periode',
            'biaya',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran berhasil diperbarui',
            'data' => $pembayaran,
        ]);
    }
}

