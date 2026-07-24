<?php

namespace App\Http\Controllers;

use App\Models\Jenjang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenjangController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = Jenjang::query();

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
            'kode' => ['nullable', 'string', 'max:50', 'unique:jenjang,kode'],
            'nama' => ['required', 'string', 'max:255', 'unique:jenjang,nama'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $jenjang = Jenjang::create($validated);

        return response()->json($jenjang, 201);
    }

    public function show(Jenjang $jenjang): JsonResponse
    {
        return response()->json($jenjang->load('rentangNilai'));
    }

    public function update(Request $request, Jenjang $jenjang): JsonResponse
    {
        $validated = $request->validate([
            'kode' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('jenjang', 'kode')->ignore($jenjang->id),
            ],
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('jenjang', 'nama')->ignore($jenjang->id),
            ],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $jenjang->update($validated);

        return response()->json($jenjang);
    }

    public function destroy(Jenjang $jenjang): JsonResponse
    {
        $jenjang->delete();

        return response()->json(['message' => 'Jenjang dihapus']);
    }
}

