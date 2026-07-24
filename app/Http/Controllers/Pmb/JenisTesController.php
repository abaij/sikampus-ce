<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbJenisTes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisTesController extends Controller
{
    /**
     * Daftar jenis tes masuk (paginasi + pencarian) — hanya admin (auth).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = PmbJenisTes::query()->orderBy('nama');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:pmb_jenis_tes,nama'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'is_aktif' => ['sometimes', 'boolean'],
            'is_wajib' => ['sometimes', 'boolean'],
        ]);

        if (! array_key_exists('is_aktif', $validated)) {
            $validated['is_aktif'] = true;
        }
        if (! array_key_exists('is_wajib', $validated)) {
            $validated['is_wajib'] = false;
        }

        $jenis = PmbJenisTes::create($validated);

        return response()->json($jenis, 201);
    }

    public function show(PmbJenisTes $jenis_te): JsonResponse
    {
        return response()->json($jenis_te);
    }

    public function update(Request $request, PmbJenisTes $jenis_te): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('pmb_jenis_tes', 'nama')->ignore($jenis_te->id),
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'is_aktif' => ['sometimes', 'boolean'],
            'is_wajib' => ['sometimes', 'boolean'],
        ]);

        $jenis_te->update($validated);

        return response()->json($jenis_te->fresh());
    }

    public function destroy(PmbJenisTes $jenis_te): JsonResponse
    {
        $jenis_te->delete();

        return response()->json(['message' => 'Jenis tes masuk dihapus']);
    }
}
