<?php

namespace App\Http\Controllers;

use App\Models\AturanAksesKeuangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AturanAksesKeuanganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status');

        $query = AturanAksesKeuangan::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_akses', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $data = $query->orderBy('kode_akses')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_akses' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:aturan_akses_keuangan,kode_akses'],
            'nama' => ['nullable', 'string', 'max:255'],
            'persentase_minimum' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ], [
            'kode_akses.required' => 'Kode akses wajib diisi.',
            'kode_akses.regex' => 'Kode akses hanya huruf kecil, angka, dan underscore.',
            'kode_akses.unique' => 'Kode akses sudah digunakan.',
            'persentase_minimum.min' => 'Persentase minimal tidak boleh negatif.',
            'persentase_minimum.max' => 'Persentase maksimal 100.',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        $row = AturanAksesKeuangan::create($validated);

        return response()->json($row, 201);
    }

    public function show(AturanAksesKeuangan $aturan_akses_keuangan): JsonResponse
    {
        return response()->json($aturan_akses_keuangan);
    }

    public function update(Request $request, AturanAksesKeuangan $aturan_akses_keuangan): JsonResponse
    {
        $validated = $request->validate([
            'kode_akses' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('aturan_akses_keuangan', 'kode_akses')->ignore($aturan_akses_keuangan->id),
            ],
            'nama' => ['nullable', 'string', 'max:255'],
            'persentase_minimum' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ], [
            'kode_akses.regex' => 'Kode akses hanya huruf kecil, angka, dan underscore.',
            'persentase_minimum.min' => 'Persentase minimal tidak boleh negatif.',
            'persentase_minimum.max' => 'Persentase maksimal 100.',
        ]);

        $aturan_akses_keuangan->update($validated);

        return response()->json($aturan_akses_keuangan->fresh());
    }

    public function destroy(AturanAksesKeuangan $aturan_akses_keuangan): JsonResponse
    {
        $aturan_akses_keuangan->delete();

        return response()->json(['message' => 'Aturan akses keuangan dihapus']);
    }
}
