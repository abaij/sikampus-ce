<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /**
     * Daftar notifikasi milik user yang sedang login, terbaru dahulu.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);

        $data = Notifikasi::where('id_user', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($data);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notifikasi::where('id_user', $request->user()->id)
            ->belumDibaca()
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markAsRead(Request $request, Notifikasi $notifikasi): JsonResponse
    {
        if ($notifikasi->id_user !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke notifikasi ini.');
        }

        if ($notifikasi->dibaca_pada === null) {
            $notifikasi->update(['dibaca_pada' => now()]);
        }

        return response()->json($notifikasi);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notifikasi::where('id_user', $request->user()->id)
            ->belumDibaca()
            ->update(['dibaca_pada' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca']);
    }
}
