<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SemesterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $isActive = $request->get('is_active');

        $query = Semester::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $data = $query->orderBy('kode', 'desc')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:semester,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        if ($validated['is_active'] ?? false) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        $semester = Semester::create($validated);

        return response()->json($semester, 201);
    }

    public function show(Semester $semester): JsonResponse
    {
        return response()->json($semester);
    }

    public function update(Request $request, Semester $semester): JsonResponse
    {
        $validated = $request->validate([
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('semester', 'kode')->ignore($semester->id),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        if (isset($validated['is_active']) && $validated['is_active'] && !$semester->is_active) {
            Semester::where('is_active', true)->where('id', '!=', $semester->id)->update(['is_active' => false]);
        }

        $semester->update($validated);

        return response()->json($semester);
    }

    public function destroy(Semester $semester): JsonResponse
    {
        $semester->delete();

        return response()->json(['message' => 'Semester dihapus']);
    }

    /**
     * Daftar semester untuk dropdown (pengguna terautentikasi; route tunggal di api.php).
     */
    public function getList(Request $request): JsonResponse
    {
        $semesters = Semester::orderBy('kode', 'desc')
            ->select('id', 'kode', 'nama', 'is_active')
            ->get();

        return response()->json($semesters);
    }
}

