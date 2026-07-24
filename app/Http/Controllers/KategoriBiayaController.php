<?php

namespace App\Http\Controllers;

use App\Models\KategoriBiaya;
use App\Models\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriBiayaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = KategoriBiaya::withCount([
            'kategori_biaya_mahasiswa as jumlah_mahasiswa' => function ($q) {
                $q->where('status', 'active');
            },
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['nullable', 'string', 'max:50', 'unique:kategori_biaya,kode'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        if (! isset($validated['status'])) {
            $validated['status'] = 'active';
        }

        $kategoriBiaya = KategoriBiaya::create($validated);

        return response()->json($kategoriBiaya, 201);
    }

    public function show(KategoriBiaya $kategori_biaya): JsonResponse
    {
        $kategori_biaya->loadCount([
            'kategori_biaya_mahasiswa as jumlah_mahasiswa' => function ($q) {
                $q->where('status', 'active');
            },
        ]);

        return response()->json($kategori_biaya);
    }

    /**
     * List mahasiswa yang termasuk pada kategori biaya ini (untuk halaman detail).
     */
    public function mahasiswa(Request $request, KategoriBiaya $kategori_biaya): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = Mahasiswa::with([
            'prodi',
            'status_akademik',
            'kategori_biaya_mahasiswa' => function ($q) use ($kategori_biaya) {
                $q->where('id_kategori_biaya', $kategori_biaya->id)
                  ->where('status', 'active')
                  ->with('semester');
            },
        ])->whereHas('kategori_biaya_mahasiswa', function ($q) use ($kategori_biaya) {
            $q->where('id_kategori_biaya', $kategori_biaya->id)
              ->where('status', 'active');
        });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function update(Request $request, KategoriBiaya $kategori_biaya): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('kategori_biaya', 'kode')->ignore($kategori_biaya->id),
            ],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $kategori_biaya->update($validated);

        return response()->json($kategori_biaya);
    }

    public function destroy(KategoriBiaya $kategori_biaya): JsonResponse
    {
        $kategori_biaya->delete();

        return response()->json(['message' => 'Kategori biaya dihapus']);
    }
}
