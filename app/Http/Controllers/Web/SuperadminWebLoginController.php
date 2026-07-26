<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SuperadminWebLoginController extends Controller
{
    /**
     * Panel maintenance superadmin (konfigurasi env, migrasi, test upload) — login-nya sendiri
     * kini disatukan di LoginController pada halaman utama ("/"); superadmin yang sudah
     * login lewat sana tetap bisa membuka halaman ini karena sesinya sama (guard "web").
     */
    public function dashboard(): View
    {
        return view('dashboard');
    }
}
