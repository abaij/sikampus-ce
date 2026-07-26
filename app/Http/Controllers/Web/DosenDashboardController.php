<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DosenDashboardController extends Controller
{
    /**
     * Placeholder dashboard dosen — modulnya menyusul.
     */
    public function index(): View
    {
        return view('dosen.dashboard');
    }
}
