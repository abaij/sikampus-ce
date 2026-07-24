<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperadmin
{
    /**
     * Batasi akses ke endpoint yang memberi/mengubah hak istimewa (role, scope, permission
     * pengguna lain) hanya untuk Superadmin. Dipasang di dalam grup role.admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->isSuperadmin()) {
            return response()->json([
                'message' => 'Akses ditolak. Hanya Superadmin yang dapat mengakses resource ini.',
            ], 403);
        }

        return $next($request);
    }
}
