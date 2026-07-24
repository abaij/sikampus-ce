<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\JalurMasuk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JalurMasukController extends Controller
{
    /**
     * List jalur masuk. Public (no auth): returns all for registration.
     * Admin (auth + per_page): returns paginated with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 0);
        $search = $request->get('search');
        $status = $request->get('status');
        $isAdminList = $request->user() && ($perPage > 0 || $search || $status);

        $query = JalurMasuk::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $query->orderBy('nama');

        if ($isAdminList && $perPage > 0) {
            return response()->json($query->paginate($perPage));
        }

        return response()->json([
            'success' => true,
            'message' => 'Data jalur masuk berhasil diambil',
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jalur_masuk,nama'],
            'deskripsi' => ['nullable', 'string'],
            'is_free_of_charge' => ['sometimes', 'boolean'],
            'has_selection' => ['sometimes', 'boolean'],
            'has_interview' => ['sometimes', 'boolean'],
            'has_physical_test' => ['sometimes', 'boolean'],
            'has_psychological_test' => ['sometimes', 'boolean'],
            'has_medical_test' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $jalurMasuk = JalurMasuk::create($validated);

        return response()->json($jalurMasuk, 201);
    }

    public function show(JalurMasuk $jalurMasuk): JsonResponse
    {
        return response()->json($jalurMasuk);
    }

    public function update(Request $request, JalurMasuk $jalurMasuk): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('jalur_masuk', 'nama')->ignore($jalurMasuk->id),
            ],
            'deskripsi' => ['nullable', 'string'],
            'is_free_of_charge' => ['sometimes', 'boolean'],
            'has_selection' => ['sometimes', 'boolean'],
            'has_interview' => ['sometimes', 'boolean'],
            'has_physical_test' => ['sometimes', 'boolean'],
            'has_psychological_test' => ['sometimes', 'boolean'],
            'has_medical_test' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $jalurMasuk->update($validated);

        return response()->json($jalurMasuk);
    }

    public function destroy(JalurMasuk $jalurMasuk): JsonResponse
    {
        $jalurMasuk->delete();

        return response()->json(['message' => 'Jalur masuk dihapus']);
    }
}
