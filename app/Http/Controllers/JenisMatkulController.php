<?php

namespace App\Http\Controllers;

use App\Models\JenisMatkul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisMatkulController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = JenisMatkul::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jenis_matkul,nama'],
            'kode' => ['required', 'string', 'max:50', 'unique:jenis_matkul,kode'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $jenisMatkul = JenisMatkul::create($validated);

        return response()->json($jenisMatkul, 201);
    }

    public function show(JenisMatkul $jenisMatkul): JsonResponse
    {
        return response()->json($jenisMatkul);
    }

    public function update(Request $request, JenisMatkul $jenisMatkul): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('jenis_matkul', 'nama')->ignore($jenisMatkul->id),
            ],
            'kode' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('jenis_matkul', 'kode')->ignore($jenisMatkul->id)],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $jenisMatkul->update($validated);

        return response()->json($jenisMatkul);
    }

    public function destroy(JenisMatkul $jenisMatkul): JsonResponse
    {
        $jenisMatkul->delete();

        return response()->json(['message' => 'Jenis mata kuliah dihapus']);
    }
}

