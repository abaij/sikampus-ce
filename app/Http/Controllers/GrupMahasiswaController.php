<?php

namespace App\Http\Controllers;

use App\Models\GrupMahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GrupMahasiswaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = GrupMahasiswa::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('angkatan', 'desc')->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['nullable', 'string', 'max:50', 'unique:grup_mahasiswa,kode'],
            'angkatan' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $grup = GrupMahasiswa::create($validated);

        return response()->json($grup, 201);
    }

    public function show(GrupMahasiswa $grupMahasiswa): JsonResponse
    {
        return response()->json($grupMahasiswa);
    }

    public function update(Request $request, GrupMahasiswa $grupMahasiswa): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('grup_mahasiswa', 'kode')->ignore($grupMahasiswa->id),
            ],
            'angkatan' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $grupMahasiswa->update($validated);

        return response()->json($grupMahasiswa);
    }

    public function destroy(GrupMahasiswa $grupMahasiswa): JsonResponse
    {
        $grupMahasiswa->delete();

        return response()->json(['message' => 'Grup mahasiswa dihapus']);
    }
}

