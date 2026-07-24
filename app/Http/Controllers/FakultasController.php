<?php

namespace App\Http\Controllers;

use App\Models\Fakultas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FakultasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status', NULL);

        $query = Fakultas::query();
        $query->with('dekan');

        $user = $request->user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (!empty($fakultasScopeIds)) {
                $query->whereIn('id', $fakultasScopeIds);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasFakultasScope()) {
            abort(403, 'Anda hanya dapat mengakses fakultas dalam scope Anda. Pembuatan fakultas baru tidak diizinkan.');
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'nama' => ['required', 'string', 'max:255', 'unique:fakultas,nama'],
            'kode' => ['nullable', 'string', 'max:50', 'unique:fakultas,kode'],
            'deskripsi' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'negara' => ['nullable', 'string', 'max:100'],
            'id_dekan' => ['nullable', 'integer', 'exists:dosen,id'],
        ]);

        $fakultas = Fakultas::create($validated);

        return response()->json($fakultas, 201);
    }

    public function show(Request $request, Fakultas $fakulta): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (!empty($fakultasScopeIds) && !in_array((int) $fakulta->id, $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        return response()->json($fakulta);
    }

    public function update(Request $request, Fakultas $fakulta): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (!empty($fakultasScopeIds) && !in_array((int) $fakulta->id, $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('fakultas', 'nama')->ignore($fakulta->id),
            ],
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('fakultas', 'kode')->ignore($fakulta->id),
            ],
            'deskripsi' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'negara' => ['nullable', 'string', 'max:100'],
            'id_dekan' => ['nullable', 'integer', 'exists:dosen,id'],
        ]);

        $validated['status'] = $validated['status'] === 'inactive' ? 'inactive' : 'active';

        $fakulta->update($validated);

        return response()->json($fakulta);
    }

    public function destroy(Request $request, Fakultas $fakulta): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (!empty($fakultasScopeIds) && !in_array((int) $fakulta->id, $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        $fakulta->delete();

        return response()->json(['message' => 'Fakultas dihapus']);
    }
}

