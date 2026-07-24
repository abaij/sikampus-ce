<?php

namespace App\Http\Controllers;

use App\Models\JenisKuliah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisKuliahController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = JenisKuliah::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }
}

