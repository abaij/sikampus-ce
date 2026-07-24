<?php

namespace App\Http\Controllers;

use App\Models\Agama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgamaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = Agama::query();

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }
}
