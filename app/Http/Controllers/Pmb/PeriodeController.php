<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbPeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodeController extends Controller
{
    /**
     * Menampilkan daftar periode dengan pagination dan search.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = PmbPeriode::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
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
     * Menyimpan periode baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['required', 'string', 'max:100', 'unique:pmb_periode,kode'],
            'keterangan' => ['nullable', 'string'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'is_active' => ['nullable', 'boolean'],
            'pilih_prodi_max' => ['nullable', 'integer', 'min:1'],
        ]);

        $periode = PmbPeriode::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil ditambahkan',
            'data' => $periode,
        ], 201);
    }

    /**
     * Menampilkan detail periode.
     */
    public function show(PmbPeriode $periode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $periode,
        ]);
    }

    /**
     * Mengupdate periode.
     */
    public function update(Request $request, PmbPeriode $periode): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('pmb_periode', 'kode')->ignore($periode->id),
            ],
            'keterangan' => ['nullable', 'string'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'is_active' => ['nullable', 'boolean'],
            'pilih_prodi_max' => ['nullable', 'integer', 'min:1'],
        ]);

        $periode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil diupdate',
            'data' => $periode,
        ]);
    }

    /**
     * Menghapus periode (soft delete).
     */
    public function destroy(PmbPeriode $periode): JsonResponse
    {
        $periode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil dihapus',
        ]);
    }
}

