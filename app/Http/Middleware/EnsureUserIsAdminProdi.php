<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminProdi
{
    /**
     * Dosen yang ditetapkan sebagai Kepala Prodi (id_kaprodi) atau Sekretaris Prodi (id_sekprodi).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasProdiScope()) {
            return response()->json([
                'message' => 'Akses ditolak. Hanya Kepala Prodi atau Sekretaris Prodi (dosen) yang dapat mengakses resource ini.',
            ], 403);
        }

        return $next($request);
    }
}
