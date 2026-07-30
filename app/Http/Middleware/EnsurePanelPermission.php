<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PanelAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelPermission
{
    /**
     * Penjaga per-modul di dalam panel admin: role.admin.web hanya menentukan "boleh masuk
     * panel", middleware ini menentukan "boleh masuk modul yang mana" berdasarkan peta
     * config/panel_access.php. Dipasang sekali di grup rute admin, jadi setiap rute baru
     * otomatis ikut terjaga begitu prefiksnya didaftarkan di peta tersebut.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! PanelAccess::allows($user, $request->route()?->getName())) {
            abort(403, 'Akses ditolak. Anda tidak memiliki hak akses untuk modul ini.');
        }

        return $next($request);
    }
}
