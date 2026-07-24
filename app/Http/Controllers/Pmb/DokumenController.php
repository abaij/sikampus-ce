<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbDokumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokumenController extends Controller
{
    /**
     * Perbarui status verifikasi dokumen PMB (admin).
     */
    public function updateStatus(Request $request, PmbDokumen $dokumen): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,verified,rejected',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $dokumen->update([
            'status' => $validated['status'],
            'keterangan' => $validated['keterangan'] ?? $dokumen->keterangan,
        ]);

        $dokumen->load(['persyaratan']);

        return response()->json([
            'success' => true,
            'message' => 'Status dokumen berhasil diperbarui',
            'data' => $dokumen,
        ]);
    }
}
