<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbPersyaratan;
use App\Models\PmbPeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersyaratanController extends Controller
{
    /**
     * Menampilkan daftar persyaratan dengan pagination dan search.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $idPeriode = $request->get('id_periode');

        $query = PmbPersyaratan::with('periode');

        if ($idPeriode) {
            $query->where('id_periode', $idPeriode);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Menyimpan persyaratan baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['required', 'exists:pmb_periode,id'],
            'nama' => ['required', 'string', 'max:255', 'unique:pmb_persyaratan,nama'],
            'keterangan' => ['nullable', 'string'],
            'is_wajib' => ['nullable', 'boolean'],
        ]);

        $persyaratan = PmbPersyaratan::create($validated);
        $persyaratan->load('periode');

        return response()->json([
            'success' => true,
            'message' => 'Persyaratan berhasil ditambahkan',
            'data' => $persyaratan,
        ], 201);
    }

    /**
     * Menampilkan detail persyaratan.
     */
    public function show(PmbPersyaratan $persyaratan): JsonResponse
    {
        $persyaratan->load('periode');

        return response()->json([
            'success' => true,
            'data' => $persyaratan,
        ]);
    }

    /**
     * Mengupdate persyaratan.
     */
    public function update(Request $request, PmbPersyaratan $persyaratan): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['sometimes', 'required', 'exists:pmb_periode,id'],
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('pmb_persyaratan', 'nama')->ignore($persyaratan->id),
            ],
            'keterangan' => ['nullable', 'string'],
            'is_wajib' => ['nullable', 'boolean'],
        ]);

        $persyaratan->update($validated);
        $persyaratan->load('periode');

        return response()->json([
            'success' => true,
            'message' => 'Persyaratan berhasil diupdate',
            'data' => $persyaratan,
        ]);
    }

    /**
     * Menghapus persyaratan (soft delete).
     */
    public function destroy(PmbPersyaratan $persyaratan): JsonResponse
    {
        $persyaratan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Persyaratan berhasil dihapus',
        ]);
    }
}

