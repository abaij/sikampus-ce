<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    /**
     * Daftar FAQ dengan pagination, filter periode, dan pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $idPeriode = $request->get('id_periode');

        $query = PmbFaq::with('periode');

        if ($idPeriode) {
            $query->where('id_periode', $idPeriode);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pertanyaan', 'like', "%{$search}%")
                    ->orWhere('jawaban', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('urutan')->orderBy('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['required', 'exists:pmb_periode,id'],
            'pertanyaan' => [
                'required',
                'string',
                'max:500',
                Rule::unique('pmb_faq', 'pertanyaan')->where(fn ($q) => $q->where('id_periode', $request->id_periode)),
            ],
            'jawaban' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]);

        $faq = PmbFaq::create($validated);
        $faq->load('periode');

        return response()->json([
            'success' => true,
            'message' => 'FAQ berhasil ditambahkan',
            'data' => $faq,
        ], 201);
    }

    public function show(PmbFaq $faq): JsonResponse
    {
        $faq->load('periode');

        return response()->json([
            'success' => true,
            'data' => $faq,
        ]);
    }

    public function update(Request $request, PmbFaq $faq): JsonResponse
    {
        $periodeId = (int) $request->input('id_periode', $faq->id_periode);

        $validated = $request->validate([
            'id_periode' => ['sometimes', 'required', 'exists:pmb_periode,id'],
            'pertanyaan' => [
                'sometimes',
                'required',
                'string',
                'max:500',
                Rule::unique('pmb_faq', 'pertanyaan')
                    ->where(fn ($q) => $q->where('id_periode', $periodeId))
                    ->ignore($faq->id),
            ],
            'jawaban' => ['sometimes', 'required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]);

        $faq->update($validated);
        $faq->load('periode');

        return response()->json([
            'success' => true,
            'message' => 'FAQ berhasil diperbarui',
            'data' => $faq,
        ]);
    }

    public function destroy(PmbFaq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ berhasil dihapus',
        ]);
    }
}
