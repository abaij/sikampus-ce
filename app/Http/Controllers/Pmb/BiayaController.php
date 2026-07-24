<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbBiaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BiayaController extends Controller
{
    /**
     * Menampilkan daftar biaya dengan pagination dan search.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $idPeriode = $request->get('id_periode');

        $query = PmbBiaya::with('periode');

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
     * Menyimpan biaya baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['required', 'exists:pmb_periode,id'],
            'nama' => ['required', 'string', 'max:255', 'unique:pmb_biaya,nama'],
            'keterangan' => ['nullable', 'string'],
            'jumlah' => ['nullable', 'numeric', 'min:0'],
        ]);

        $biaya = PmbBiaya::create($validated);
        $biaya->load('periode');

        return response()->json([
            'success' => true,
            'message' => 'Biaya berhasil ditambahkan',
            'data' => $biaya,
        ], 201);
    }

    /**
     * Menampilkan detail biaya.
     */
    public function show(PmbBiaya $biaya): JsonResponse
    {
        $biaya->load('periode');

        return response()->json([
            'success' => true,
            'data' => $biaya,
        ]);
    }

    /**
     * Mengupdate biaya.
     */
    public function update(Request $request, PmbBiaya $biaya): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['sometimes', 'required', 'exists:pmb_periode,id'],
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('pmb_biaya', 'nama')->ignore($biaya->id),
            ],
            'keterangan' => ['nullable', 'string'],
            'jumlah' => ['nullable', 'numeric', 'min:0'],
        ]);

        $biaya->update($validated);
        $biaya->load('periode');

        return response()->json([
            'success' => true,
            'message' => 'Biaya berhasil diupdate',
            'data' => $biaya,
        ]);
    }

    /**
     * Menghapus biaya (soft delete).
     */
    public function destroy(PmbBiaya $biaya): JsonResponse
    {
        $biaya->delete();

        return response()->json([
            'success' => true,
            'message' => 'Biaya berhasil dihapus',
        ]);
    }
}

