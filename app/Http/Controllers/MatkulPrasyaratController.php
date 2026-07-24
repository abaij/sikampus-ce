<?php

namespace App\Http\Controllers;

use App\Models\Matkul;
use App\Models\MatkulPrasyarat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatkulPrasyaratController extends Controller
{
    private function ensureMatkulAccess(Request $request, Matkul $matkul): void
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }
    }

    public function index(Request $request, Matkul $matkul): JsonResponse
    {
        $this->ensureMatkulAccess($request, $matkul);

        $rows = MatkulPrasyarat::with(['matkulPrasyarat.prodi.jenjang', 'matkulPrasyarat.jenisMatkul'])
            ->where('id_matkul', $matkul->id)
            ->orderBy('id')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request, Matkul $matkul): JsonResponse
    {
        $this->ensureMatkulAccess($request, $matkul);

        $validated = $request->validate([
            'id_matkul_prasyarat' => [
                'required',
                'integer',
                'exists:matkul,id',
                Rule::notIn([$matkul->id]),
            ],
        ]);

        $prasyaratId = (int) $validated['id_matkul_prasyarat'];

        $prasyaratMk = Matkul::find($prasyaratId);
        if (! $prasyaratMk) {
            return response()->json(['message' => 'Mata kuliah prasyarat tidak ditemukan.'], 422);
        }
        if ($matkul->id_prodi != $prasyaratMk->id_prodi) {
            return response()->json([
                'message' => 'Mata kuliah prasyarat harus dari program studi yang sama.',
            ], 422);
        }

        if (MatkulPrasyarat::where('id_matkul', $matkul->id)
            ->where('id_matkul_prasyarat', $prasyaratId)
            ->exists()) {
            return response()->json([
                'message' => 'Mata kuliah prasyarat ini sudah terdaftar.',
            ], 422);
        }

        if (MatkulPrasyarat::where('id_matkul', $prasyaratId)
            ->where('id_matkul_prasyarat', $matkul->id)
            ->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menambahkan prasyarat: mata kuliah yang dipilih sudah mensyaratkan mata kuliah ini (siklus).',
            ], 422);
        }

        $row = MatkulPrasyarat::create([
            'id_matkul' => $matkul->id,
            'id_matkul_prasyarat' => $prasyaratId,
        ]);

        return response()->json(
            $row->load(['matkulPrasyarat.prodi.jenjang', 'matkulPrasyarat.jenisMatkul']),
            201
        );
    }

    public function update(Request $request, Matkul $matkul, MatkulPrasyarat $matkulPrasyarat): JsonResponse
    {
        $this->ensureMatkulAccess($request, $matkul);

        if ($matkulPrasyarat->id_matkul !== $matkul->id) {
            abort(404);
        }

        $validated = $request->validate([
            'id_matkul_prasyarat' => [
                'required',
                'integer',
                'exists:matkul,id',
                Rule::notIn([$matkul->id]),
            ],
        ]);

        $prasyaratId = (int) $validated['id_matkul_prasyarat'];

        $prasyaratMk = Matkul::find($prasyaratId);
        if (! $prasyaratMk) {
            return response()->json(['message' => 'Mata kuliah prasyarat tidak ditemukan.'], 422);
        }
        if ($matkul->id_prodi != $prasyaratMk->id_prodi) {
            return response()->json([
                'message' => 'Mata kuliah prasyarat harus dari program studi yang sama.',
            ], 422);
        }

        if ($prasyaratId !== $matkulPrasyarat->id_matkul_prasyarat) {
            $duplicate = MatkulPrasyarat::where('id_matkul', $matkul->id)
                ->where('id_matkul_prasyarat', $prasyaratId)
                ->where('id', '!=', $matkulPrasyarat->id)
                ->exists();
            if ($duplicate) {
                return response()->json([
                    'message' => 'Mata kuliah prasyarat ini sudah terdaftar.',
                ], 422);
            }

            if (MatkulPrasyarat::where('id_matkul', $prasyaratId)
                ->where('id_matkul_prasyarat', $matkul->id)
                ->exists()) {
                return response()->json([
                    'message' => 'Tidak dapat mengubah prasyarat: mata kuliah yang dipilih sudah mensyaratkan mata kuliah ini (siklus).',
                ], 422);
            }
        }

        $matkulPrasyarat->update([
            'id_matkul_prasyarat' => $prasyaratId,
        ]);

        return response()->json(
            $matkulPrasyarat->fresh()->load(['matkulPrasyarat.prodi.jenjang', 'matkulPrasyarat.jenisMatkul'])
        );
    }

    public function destroy(Request $request, Matkul $matkul, MatkulPrasyarat $matkulPrasyarat): JsonResponse
    {
        $this->ensureMatkulAccess($request, $matkul);

        if ($matkulPrasyarat->id_matkul !== $matkul->id) {
            abort(404);
        }

        $matkulPrasyarat->delete();

        return response()->json(['message' => 'Prasyarat dihapus']);
    }
}
