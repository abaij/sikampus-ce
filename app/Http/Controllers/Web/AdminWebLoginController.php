<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminWebLoginController extends Controller
{
    /**
     * Sesi web panel admin (superadmin/akademik/keuangan) — login-nya sendiri kini disatukan
     * di LoginController pada halaman utama ("/"); controller ini hanya menyisakan logout dan
     * tampilan dashboard Livewire panel admin.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard');
    }
}
