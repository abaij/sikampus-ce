<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminWeb
{
    /**
     * Versi web (redirect/abort, bukan JSON) dari role.admin — dipakai oleh route Livewire
     * panel admin di routes/web.php. Predikat role sama persis dengan EnsureUserIsAdmin
     * (versi JSON di routes/api.php), supaya definisi "admin" konsisten di kedua sisi.
     *
     * Beda dengan EnsureUserIsSuperadminWeb: user yang sudah login tapi rolenya tidak cocok
     * TIDAK di-logout paksa (mis. user keuangan membuka halaman akademik-only) — cukup
     * ditampilkan halaman 403, bukan dilempar balik ke layar login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $hasAdminRole = $user->hasAnyRole(['superadmin', 'akademik', 'keuangan', 'Superadmin', 'Akademik', 'Keuangan']);

        if (! $hasAdminRole) {
            abort(403, 'Akses ditolak. Hanya admin yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
