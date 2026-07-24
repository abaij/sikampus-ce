<?php

namespace App\Http\Controllers;

use App\Models\JenisPenilaian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisPenilaianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = JenisPenilaian::query();

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
            'kode' => ['required', 'string', 'max:50', 'unique:jenis_penilaian,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'bobot' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['manual', 'otomatis'])],
        ]);

        // Validasi total bobot tidak melebihi 100%
        $totalBobot = JenisPenilaian::sum('bobot');
        $totalBobotBaru = $totalBobot + $validated['bobot'];

        if ($totalBobotBaru > 100) {
            return response()->json([
                'message' => 'Total bobot semua jenis penilaian tidak boleh melebihi 100%.',
                'errors' => [
                    'bobot' => [
                        'Total bobot saat ini: ' . $totalBobot . '%. ' .
                        'Dengan bobot baru (' . $validated['bobot'] . '%), total menjadi ' . $totalBobotBaru . '% yang melebihi batas maksimal 100%.'
                    ]
                ]
            ], 422);
        }

        $jenisPenilaian = JenisPenilaian::create($validated);

        return response()->json($jenisPenilaian, 201);
    }

    public function show(JenisPenilaian $jenisPenilaian): JsonResponse
    {
        return response()->json($jenisPenilaian);
    }

    public function update(Request $request, JenisPenilaian $jenisPenilaian): JsonResponse
    {
        $validated = $request->validate([
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('jenis_penilaian', 'kode')->ignore($jenisPenilaian->id),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'bobot' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['manual', 'otomatis'])],
        ]);

        // Validasi total bobot tidak melebihi 100% (jika bobot diupdate)
        if (isset($validated['bobot'])) {
            // Hitung total bobot semua jenis penilaian kecuali yang sedang diupdate
            $totalBobotLain = JenisPenilaian::where('id', '!=', $jenisPenilaian->id)->sum('bobot');
            $totalBobotBaru = $totalBobotLain + $validated['bobot'];

            if ($totalBobotBaru > 100) {
                return response()->json([
                    'message' => 'Total bobot semua jenis penilaian tidak boleh melebihi 100%.',
                    'errors' => [
                        'bobot' => [
                            'Total bobot jenis penilaian lain: ' . $totalBobotLain . '%. ' .
                            'Dengan bobot baru (' . $validated['bobot'] . '%), total menjadi ' . $totalBobotBaru . '% yang melebihi batas maksimal 100%.'
                        ]
                    ]
                ], 422);
            }
        }

        $jenisPenilaian->update($validated);

        return response()->json($jenisPenilaian);
    }

    public function destroy(JenisPenilaian $jenisPenilaian): JsonResponse
    {
        $jenisPenilaian->delete();

        return response()->json(['message' => 'Jenis penilaian dihapus']);
    }
}

