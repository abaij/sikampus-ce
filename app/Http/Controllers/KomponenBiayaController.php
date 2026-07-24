<?php

namespace App\Http\Controllers;

use App\Models\KomponenBiaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KomponenBiayaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = KomponenBiaya::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('kode')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:komponen_biaya,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'is_per_semester' => ['nullable', 'boolean'],
            'is_akademik' => ['nullable', 'boolean'],
        ]);

        $komponenBiaya = KomponenBiaya::create($validated);

        return response()->json($komponenBiaya, 201);
    }

    public function show(KomponenBiaya $komponenBiaya): JsonResponse
    {
        return response()->json($komponenBiaya);
    }

    public function update(Request $request, KomponenBiaya $komponenBiaya): JsonResponse
    {
        $validated = $request->validate([
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('komponen_biaya', 'kode')->ignore($komponenBiaya->id),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'is_per_semester' => ['nullable', 'boolean'],
            'is_akademik' => ['nullable', 'boolean'],
        ]);

        $komponenBiaya->update($validated);

        return response()->json($komponenBiaya);
    }

    public function destroy(KomponenBiaya $komponenBiaya): JsonResponse
    {
        $komponenBiaya->delete();

        return response()->json(['message' => 'Komponen biaya dihapus']);
    }
}
