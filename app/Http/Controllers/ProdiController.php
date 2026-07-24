<?php

namespace App\Http\Controllers;

use App\Models\Prodi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProdiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $fakultasId = $request->get('id_fakultas');

        $query = Prodi::with(['fakultas', 'kaprodi', 'sekprodi', 'jenjang', 'semesterAktif']);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id', $allowedProdiIds);
            }

            $fakultasScopeIds = $user->getFakultasScopeIds();
            if ($fakultasId && ! empty($fakultasScopeIds) && ! in_array((int) $fakultasId, $fakultasScopeIds, true)) {
                $fakultasId = null;
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($fakultasId) {
            $query->where('id_fakultas', $fakultasId);
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nama_en' => ['nullable', 'string', 'max:255'],
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
            'id_sekprodi' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_jenjang' => ['nullable', 'integer', 'exists:jenjang,id'],
            'id_semester_aktif' => ['nullable', 'integer', 'exists:semester,id'],
            'sks_minimal' => ['nullable', 'integer', 'min:0'],
            'ipk_lulus_minimal' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'gelar' => ['nullable', 'string', 'max:100'],
            'gelar_singkat' => ['nullable', 'string', 'max:20'],
            'maks_dosen_pembimbing' => ['nullable', 'integer', 'min:1'],
            'maks_dosen_penguji' => ['nullable', 'integer', 'min:1'],
            'is_pmb_open' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (empty($fakultasScopeIds)) {
                // Admin ber-scope prodi saja (tanpa fakultas) tidak berwenang membuat prodi baru.
                abort(403, 'Anda hanya dapat mengakses program studi dalam scope Anda. Pembuatan program studi baru tidak diizinkan.');
            }
            if (isset($validated['id_fakultas']) && ! in_array((int) $validated['id_fakultas'], $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        $prodi = Prodi::create($validated);

        return response()->json($prodi->load(['fakultas', 'kaprodi', 'sekprodi', 'jenjang', 'semesterAktif']), 201);
    }

    public function show(Request $request, Prodi $prodi): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $prodi->id, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        return response()->json($prodi->load(['fakultas', 'kaprodi', 'sekprodi', 'jenjang', 'semesterAktif']));
    }

    public function update(Request $request, Prodi $prodi): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $prodi->id, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('prodi', 'nama')->ignore($prodi->id),
            ],
            'nama_en' => ['nullable', 'string', 'max:255'],
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
            'id_sekprodi' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_jenjang' => ['nullable', 'integer', 'exists:jenjang,id'],
            'id_semester_aktif' => ['nullable', 'integer', 'exists:semester,id'],
            'sks_minimal' => ['nullable', 'integer', 'min:0'],
            'ipk_lulus_minimal' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'gelar' => ['nullable', 'string', 'max:100'],
            'gelar_singkat' => ['nullable', 'string', 'max:20'],
            'maks_dosen_pembimbing' => ['nullable', 'integer', 'min:1'],
            'maks_dosen_penguji' => ['nullable', 'integer', 'min:1'],
            'is_pmb_open' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        if ($user && $user->hasScopeRestriction() && isset($validated['id_fakultas'])) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            // Tanpa scope fakultas sama sekali, admin ber-scope prodi tidak berwenang memindah prodi ke fakultas manapun.
            if (empty($fakultasScopeIds) || ! in_array((int) $validated['id_fakultas'], $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        $prodi->update($validated);

        return response()->json($prodi->load(['fakultas', 'kaprodi', 'sekprodi', 'jenjang', 'semesterAktif']));
    }

    public function destroy(Request $request, Prodi $prodi): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $prodi->id, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        $prodi->delete();

        return response()->json(['message' => 'Prodi dihapus']);
    }
}
