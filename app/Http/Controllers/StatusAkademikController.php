<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\StatusAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatusAkademikController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = StatusAkademik::query();

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
        $request->merge([
            'nama' => trim((string) $request->input('nama', '')),
        ]);

        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('status_akademik', 'nama')->whereNull('deleted_at'),
            ],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $trashed = StatusAkademik::onlyTrashed()
            ->where('nama', $validated['nama'])
            ->orderBy('id')
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->forceFill([
                'deskripsi' => $validated['deskripsi'] ?? null,
            ])->save();

            return response()->json($trashed->fresh(), 201);
        }

        $row = StatusAkademik::create($validated);

        return response()->json($row, 201);
    }

    public function show(StatusAkademik $statusAkademik): JsonResponse
    {
        return response()->json($statusAkademik);
    }

    public function update(Request $request, StatusAkademik $statusAkademik): JsonResponse
    {
        if ($request->has('nama')) {
            $request->merge([
                'nama' => trim((string) $request->input('nama', '')),
            ]);
        }

        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('status_akademik', 'nama')
                    ->ignore($statusAkademik->id)
                    ->whereNull('deleted_at'),
            ],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $statusAkademik->update($validated);

        return response()->json($statusAkademik->fresh());
    }

    public function destroy(StatusAkademik $statusAkademik): JsonResponse
    {
        $dipakaiMahasiswa = Mahasiswa::where('id_status_akademik', $statusAkademik->id)->exists();
        if ($dipakaiMahasiswa) {
            return response()->json([
                'message' => 'Status akademik tidak dapat dihapus karena masih dipakai oleh data mahasiswa.',
            ], 422);
        }

        $statusAkademik->delete();

        return response()->json(['message' => 'Status akademik dihapus']);
    }
}

