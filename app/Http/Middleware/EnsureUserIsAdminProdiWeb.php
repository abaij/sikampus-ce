<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdminProdiWeb
{
    /**
     * Versi web (redirect/abort, bukan JSON) dari role.admin.prodi — predikat sama persis
     * dengan EnsureUserIsAdminProdi (versi JSON di routes/api.php): dosen yang menjadi Kepala
     * Prodi atau Sekretaris Prodi (User::hasProdiScope()).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->hasProdiScope()) {
            abort(403, 'Akses ditolak. Hanya Kepala Prodi atau Sekretaris Prodi (dosen) yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
