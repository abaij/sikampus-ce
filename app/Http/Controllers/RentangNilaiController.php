<?php

namespace App\Http\Controllers;

use App\Models\RentangNilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentangNilaiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jenjangId = $request->get('id_jenjang');

        $query = RentangNilai::with('jenjang');

        if ($jenjangId) {
            $query->where('id_jenjang', $jenjangId);
        }

        $data = $query->orderByDesc('nilai_tinggi')->orderBy('nilai_huruf')->get();

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_jenjang' => ['required', 'integer', 'exists:jenjang,id'],
            'nilai_huruf' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rentang_nilai', 'nilai_huruf')->where('id_jenjang', $request->integer('id_jenjang')),
            ],
            'nilai_angka' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'nilai_rendah' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'nilai_tinggi' => ['required', 'numeric', 'min:0', 'max:999.99', 'gte:nilai_rendah'],
        ]);

        $validated['is_lulus'] = true;
        if (array_key_exists('is_lulus', $request->all())) {
            $validated['is_lulus'] = filter_var(
                $request->input('is_lulus'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? (bool) $request->input('is_lulus');
        }

        $rentangNilai = RentangNilai::create($validated);

        return response()->json($rentangNilai->load('jenjang'), 201);
    }

    public function show(RentangNilai $rentangNilai): JsonResponse
    {
        return response()->json($rentangNilai->load('jenjang'));
    }

    public function update(Request $request, RentangNilai $rentangNilai): JsonResponse
    {
        $targetJenjangId = $request->has('id_jenjang')
            ? (int) $request->get('id_jenjang')
            : (int) $rentangNilai->id_jenjang;

        $validated = $request->validate([
            'id_jenjang' => ['sometimes', 'integer', 'exists:jenjang,id'],
            'nilai_huruf' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('rentang_nilai', 'nilai_huruf')
                    ->where('id_jenjang', $targetJenjangId)
                    ->ignore($rentangNilai->id),
            ],
            'nilai_angka' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999.99'],
            'nilai_rendah' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999.99'],
            'nilai_tinggi' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999.99'],
        ]);

        if (array_key_exists('is_lulus', $request->all())) {
            $validated['is_lulus'] = filter_var(
                $request->input('is_lulus'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? (bool) $request->input('is_lulus');
        }

        $rendah = isset($validated['nilai_rendah'])
            ? (float) $validated['nilai_rendah']
            : (float) $rentangNilai->nilai_rendah;
        $tinggi = isset($validated['nilai_tinggi'])
            ? (float) $validated['nilai_tinggi']
            : (float) $rentangNilai->nilai_tinggi;
        if ($tinggi < $rendah) {
            throw ValidationException::withMessages([
                'nilai_tinggi' => ['Nilai tinggi harus lebih besar atau sama dengan nilai rendah.'],
            ]);
        }

        $rentangNilai->update($validated);

        return response()->json($rentangNilai->load('jenjang'));
    }

    public function destroy(RentangNilai $rentangNilai): JsonResponse
    {
        $rentangNilai->delete();

        return response()->json(['message' => 'Rentang nilai dihapus']);
    }
}

