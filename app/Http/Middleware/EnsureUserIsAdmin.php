<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Admin ber-scope prodi saja (tanpa fakultas) boleh akses panel admin; pembatasan data per prodi di controller (getAllowedProdiIds, dll.)

        // Spatie Permission adalah satu-satunya sumber kebenaran untuk hak admin panel.
        // Kolom users.role legacy hanya dipakai untuk membedakan tipe akun (admin/dosen/mahasiswa),
        // bukan lagi untuk bypass otorisasi panel admin.
        $hasAdminRole = $user->hasAnyRole(['superadmin', 'akademik', 'keuangan', 'Superadmin', 'Akademik', 'Keuangan']);

        if (! $hasAdminRole) {
            return response()->json([
                'message' => 'Akses ditolak. Hanya admin yang dapat mengakses resource ini.',
            ], 403);
        }

        return $next($request);
    }
}
