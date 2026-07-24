<?php

namespace App\Http\Controllers;

use App\Models\JenisKeluar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisKeluarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = JenisKeluar::query();

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }
}

