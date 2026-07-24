<?php

namespace App\Http\Controllers;

use App\Models\KategoriBiayaMahasiswa;
use App\Models\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriBiayaMahasiswaController extends Controller
{
    /**
     * List mahasiswa with prodi, status_akademik, and kategori_biaya_mahasiswa (for index view).
     * Query: search, id_prodi, id_status_akademik, id_semester_masuk,
     * id_kategori_biaya (penetapan aktif), tanpa_kategori_biaya (belum punya penetapan aktif).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $statusAkademikId = $request->get('id_status_akademik') ? (int) $request->get('id_status_akademik') : null;
        $semesterMasukId = $request->get('id_semester_masuk') ? (int) $request->get('id_semester_masuk') : null;
        $kategoriBiayaId = $request->get('id_kategori_biaya') ? (int) $request->get('id_kategori_biaya') : null;
        $tanpaKategoriBiaya = $request->boolean('tanpa_kategori_biaya');

        $query = Mahasiswa::with([
            'prodi',
            'status_akademik',
            'semester_masuk',
            'kategori_biaya_mahasiswa' => function ($q) {
                $q->where('status', 'active')->with(['kategori_biaya', 'semester']);
            },
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        if ($prodiId) {
            $query->where('id_prodi', $prodiId);
        }

        if ($statusAkademikId) {
            $query->where('id_status_akademik', $statusAkademikId);
        }

        if ($semesterMasukId) {
            $query->where('id_semester_masuk', $semesterMasukId);
        }

        if ($tanpaKategoriBiaya) {
            $query->whereDoesntHave('kategori_biaya_mahasiswa', function ($q) {
                $q->where('status', 'active');
            });
        } elseif ($kategoriBiayaId) {
            $query->whereHas('kategori_biaya_mahasiswa', function ($q) use ($kategoriBiayaId) {
                $q->where('id_kategori_biaya', $kategoriBiayaId)
                    ->where('status', 'active');
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Get all kategori_biaya_mahasiswa for one mahasiswa (for "kelola" page).
     */
    public function getByMahasiswa(int $id): JsonResponse
    {
        $items = KategoriBiayaMahasiswa::where('id_mahasiswa', $id)
            ->with(['kategori_biaya', 'semester', 'mahasiswa'])
            ->orderBy('id_semester', 'desc')
            ->get();

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => ['required', 'integer', 'exists:mahasiswa,id'],
            'id_kategori_biaya' => ['required', 'integer', 'exists:kategori_biaya,id'],
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        $exists = KategoriBiayaMahasiswa::where('id_mahasiswa', $validated['id_mahasiswa'])
            ->where('id_kategori_biaya', $validated['id_kategori_biaya'])
            ->where('id_semester', $validated['id_semester'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Kombinasi mahasiswa, kategori biaya, dan semester sudah ada.',
            ], 422);
        }

        $row = KategoriBiayaMahasiswa::create($validated);

        if ($validated['status'] === 'active') {
            KategoriBiayaMahasiswa::where('id_mahasiswa', $validated['id_mahasiswa'])
                ->where('id', '!=', $row->id)
                ->update(['status' => 'inactive']);
        }

        return response()->json($row->load(['kategori_biaya', 'semester', 'mahasiswa']), 201);
    }

    public function show(KategoriBiayaMahasiswa $kategori_biaya_mahasiswa): JsonResponse
    {
        $kategori_biaya_mahasiswa->load(['kategori_biaya', 'semester', 'mahasiswa']);

        return response()->json($kategori_biaya_mahasiswa);
    }

    public function update(Request $request, KategoriBiayaMahasiswa $kategori_biaya_mahasiswa): JsonResponse
    {
        $validated = $request->validate([
            'id_kategori_biaya' => ['sometimes', 'required', 'integer', 'exists:kategori_biaya,id'],
            'id_semester' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        if (isset($validated['id_kategori_biaya']) || isset($validated['id_semester'])) {
            $idKategori = $validated['id_kategori_biaya'] ?? $kategori_biaya_mahasiswa->id_kategori_biaya;
            $idSemester = $validated['id_semester'] ?? $kategori_biaya_mahasiswa->id_semester;
            $exists = KategoriBiayaMahasiswa::where('id_mahasiswa', $kategori_biaya_mahasiswa->id_mahasiswa)
                ->where('id_kategori_biaya', $idKategori)
                ->where('id_semester', $idSemester)
                ->where('id', '!=', $kategori_biaya_mahasiswa->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Kombinasi mahasiswa, kategori biaya, dan semester sudah ada.',
                ], 422);
            }
        }

        $kategori_biaya_mahasiswa->update($validated);

        $newStatus = $validated['status'] ?? $kategori_biaya_mahasiswa->fresh()->status;
        if ($newStatus === 'active') {
            KategoriBiayaMahasiswa::where('id_mahasiswa', $kategori_biaya_mahasiswa->id_mahasiswa)
                ->where('id', '!=', $kategori_biaya_mahasiswa->id)
                ->update(['status' => 'inactive']);
        }

        return response()->json($kategori_biaya_mahasiswa->fresh(['kategori_biaya', 'semester', 'mahasiswa']));
    }

    public function destroy(KategoriBiayaMahasiswa $kategori_biaya_mahasiswa): JsonResponse
    {
        $kategori_biaya_mahasiswa->delete();

        return response()->json(['message' => 'Kategori biaya mahasiswa dihapus']);
    }
}
