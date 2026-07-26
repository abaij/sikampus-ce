<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMahasiswaWeb
{
    /**
     * Versi web (redirect/abort, bukan JSON) dari role.mahasiswa — predikat sama persis
     * dengan EnsureUserIsMahasiswa (versi JSON di routes/api.php).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->role !== 'mahasiswa') {
            abort(403, 'Akses ditolak. Hanya mahasiswa yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
