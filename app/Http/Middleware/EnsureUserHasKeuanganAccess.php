<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasKeuanganAccess
{
    /**
     * Modul keuangan: role Spatie superadmin / keuangan (name maupun code, termasuk
     * "Superadmin" dari seeder). Spatie adalah satu-satunya sumber kebenaran — kolom
     * users.role legacy tidak lagi dipakai sebagai bypass otorisasi.
     * Digunakan setelah middleware role.admin (bukan untuk route publik).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($this->userHasKeuanganModuleAccess($user)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Akses ditolak. Hanya pengguna dengan peran keuangan atau superadmin yang dapat mengakses resource ini.',
        ], 403);
    }

    private function userHasKeuanganModuleAccess(User $user): bool
    {
        // Spatie: cocokkan name (PermissionSeeder: Superadmin, Keuangan) dan varian code/name huruf kecil
        if ($user->hasAnyRole([
            'superadmin',
            'keuangan',
            'Superadmin',
            'Keuangan',
        ])) {
            return true;
        }

        // Role lama / baris roles.code kosong: tetap izinkan lewat kolom code atau name
        return $user->roles()
            ->where(function ($q): void {
                $q->whereIn('code', ['superadmin', 'keuangan'])
                    ->orWhereIn('name', ['superadmin', 'keuangan', 'Superadmin', 'Keuangan']);
            })
            ->exists();
    }
}
