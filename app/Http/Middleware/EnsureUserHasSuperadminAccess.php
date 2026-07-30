<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSuperadminAccess
{
    /**
     * Modul superadmin-only di dalam panel admin biasa (mis. pengaturan SMTP sistem): abort 403
     * web-friendly, dipasang setelah role.admin.web — sama posturnya dengan
     * EnsureUserHasKeuanganAccess (module-specific access check), bukan versi JSON
     * (EnsureUserIsSuperadmin) atau versi yang memaksa logout (EnsureUserIsSuperadminWeb, dipakai
     * khusus area maintenance superadmin terpisah). User yang salah peran tetap admin yang sah
     * untuk halaman lain di panel ini, jadi tidak di-logout paksa.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->isSuperadmin()) {
            abort(403, 'Akses ditolak. Hanya Superadmin yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
