<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\Fakultas;
use App\Models\Jenjang;
use App\Models\Dosen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for dropdown data (fakultas, jenjang, dosen) used in PMB admin forms.
 */
class DataAcuanController extends Controller
{
    /**
     * List fakultas for dropdown (id, nama, kode)
     */
    public function fakultas(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $query = Fakultas::query()->orderBy('nama');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $data = $query->get(['id', 'nama', 'kode']);

        return response()->json(['data' => $data]);
    }

    /**
     * List jenjang for dropdown (id, nama, kode)
     */
    public function jenjang(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $query = Jenjang::query()->orderBy('nama');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $data = $query->get(['id', 'nama', 'kode']);

        return response()->json(['data' => $data]);
    }

    /**
     * List dosen for dropdown (id, nama, nip) - for kaprodi selection
     */
    public function dosen(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $query = Dosen::query()->orderBy('nama');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 100);
        $limit = $perPage > 0 ? min($perPage, 500) : 500;
        $data = $query->limit($limit)->get(['id', 'nama', 'nip']);

        return response()->json(['data' => $data]);
    }
}
