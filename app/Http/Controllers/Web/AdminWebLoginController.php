<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminWebLoginController extends Controller
{
    /**
     * Login sesi web untuk panel admin (superadmin/akademik/keuangan) — terpisah dari
     * SuperadminWebLoginController yang khusus panel maintenance superadmin di /login.
     */
    public function create(): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user instanceof User && $this->hasAdminAccess($user)) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.admin-login');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $this->hasAdminAccess($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki akses ke panel admin.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard');
    }

    /**
     * Predikat yang sama dengan EnsureUserIsAdminWeb / EnsureUserIsAdmin (JSON) — Spatie
     * adalah satu-satunya sumber kebenaran untuk hak akses panel admin.
     */
    private function hasAdminAccess(User $user): bool
    {
        return $user->hasAnyRole(['superadmin', 'akademik', 'keuangan', 'Superadmin', 'Akademik', 'Keuangan']);
    }
}
