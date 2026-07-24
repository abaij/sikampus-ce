<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\Prodi;

class ProdiController extends Controller
{
    /**
     * Get list of prodi. For public (no auth): returns all with jenjang for registration.
     * For admin (auth + per_page/search/id_fakultas): returns paginated list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 0);
        $search = $request->get('search');
        $fakultasId = $request->get('id_fakultas');
        $isAdminList = $request->user() && ($perPage > 0 || $search || $fakultasId);

        $query = Prodi::with(['fakultas', 'kaprodi', 'jenjang']);
        $query->where('is_pmb_open', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($fakultasId) {
            $query->where('id_fakultas', $fakultasId);
        }

        $query->orderBy('nama');

        if ($isAdminList && $perPage > 0) {
            return response()->json($query->paginate($perPage));
        }

        return response()->json([
            'success' => true,
            'message' => 'Data prodi berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    /**
     * Store a new prodi
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['nullable', 'string', 'max:50', 'unique:prodi,kode'],
            'deskripsi' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'negara' => ['nullable', 'string', 'max:100'],
            'id_fakultas' => ['nullable', 'integer', 'exists:fakultas,id'],
            'id_kaprodi' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_jenjang' => ['nullable', 'integer', 'exists:jenjang,id'],
        ]);

        $prodi = Prodi::create($validated);

        return response()->json($prodi->load(['fakultas', 'kaprodi', 'jenjang']), 201);
    }

    /**
     * Show a single prodi
     */
    public function show(Prodi $prodi): JsonResponse
    {
        return response()->json($prodi->load(['fakultas', 'kaprodi', 'jenjang']));
    }

    /**
     * Update a prodi
     */
    public function update(Request $request, Prodi $prodi): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('prodi', 'kode')->ignore($prodi->id),
            ],
            'deskripsi' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'negara' => ['nullable', 'string', 'max:100'],
            'id_fakultas' => ['nullable', 'integer', 'exists:fakultas,id'],
            'id_kaprodi' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_jenjang' => ['nullable', 'integer', 'exists:jenjang,id'],
        ]);

        $prodi->update($validated);

        return response()->json($prodi->load(['fakultas', 'kaprodi', 'jenjang']));
    }

    /**
     * Remove a prodi (soft delete)
     */
    public function destroy(Prodi $prodi): JsonResponse
    {
        $prodi->delete();

        return response()->json(['message' => 'Prodi dihapus']);
    }
}
