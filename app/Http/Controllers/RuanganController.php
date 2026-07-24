<?php

namespace App\Http\Controllers;

use App\Models\Ruangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RuanganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = Ruangan::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:ruangan,nama'],
            'kapasitas' => ['nullable', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $ruangan = Ruangan::create($validated);

        return response()->json($ruangan, 201);
    }

    public function show(Ruangan $ruangan): JsonResponse
    {
        return response()->json($ruangan);
    }

    public function update(Request $request, Ruangan $ruangan): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('ruangan', 'nama')->ignore($ruangan->id),
            ],
            'kapasitas' => ['nullable', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $ruangan->update($validated);

        return response()->json($ruangan);
    }

    public function destroy(Ruangan $ruangan): JsonResponse
    {
        $ruangan->delete();

        return response()->json(['message' => 'Ruangan dihapus']);
    }
}

