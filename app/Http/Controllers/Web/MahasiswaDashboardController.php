<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MahasiswaDashboardController extends Controller
{
    /**
     * Placeholder dashboard mahasiswa — modulnya menyusul.
     */
    public function index(): View
    {
        return view('mahasiswa.dashboard');
    }
}
