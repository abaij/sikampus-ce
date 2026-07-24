<?php

namespace App\Http\Controllers;

use App\Models\JenisDaftar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisDaftarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = JenisDaftar::query();

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
            'nama' => ['required', 'string', 'max:255', 'unique:jenis_daftar,nama'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $jenis = JenisDaftar::create($validated);

        return response()->json($jenis, 201);
    }

    public function show(JenisDaftar $jenisDaftar): JsonResponse
    {
        return response()->json($jenisDaftar);
    }

    public function update(Request $request, JenisDaftar $jenisDaftar): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('jenis_daftar', 'nama')->ignore($jenisDaftar->id),
            ],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $jenisDaftar->update($validated);

        return response()->json($jenisDaftar);
    }

    public function destroy(JenisDaftar $jenisDaftar): JsonResponse
    {
        $jenisDaftar->delete();

        return response()->json(['message' => 'Jenis daftar dihapus']);
    }
}

