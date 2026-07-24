<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbHasilSeleksi;
use App\Models\PmbJenisTes;
use App\Models\PmbNilaiTes;
use App\Models\PmbPendaftaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SeleksiInputController extends Controller
{
    public function show(PmbPendaftaran $pendaftaran): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->buildFormData($pendaftaran),
        ]);
    }

    public function update(Request $request, PmbPendaftaran $pendaftaran): JsonResponse
    {
        $expectedIds = $this->activeJenisTesQuery()->pluck('id')->sort()->values()->all();

        if ($expectedIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada jenis tes aktif. Tambahkan jenis tes di pengaturan terlebih dahulu.',
            ], 422);
        }

        $validated = $request->validate([
            'nilai_tes' => ['required', 'array', 'size:'.count($expectedIds)],
            'nilai_tes.*.id_jenis_tes' => ['required', 'integer', Rule::in($expectedIds)],
            'nilai_tes.*.nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'status_seleksi' => ['required', 'string', Rule::in(['lulus', 'tidak lulus'])],
            'keterangan' => ['nullable', 'string', 'max:5000'],
        ]);

        $submittedIds = collect($validated['nilai_tes'])->pluck('id_jenis_tes')->sort()->values()->all();
        $uniqueCount = collect($validated['nilai_tes'])->pluck('id_jenis_tes')->unique()->count();
        if ($uniqueCount !== count($expectedIds) || $submittedIds !== $expectedIds) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai harus diisi untuk semua jenis tes aktif, tepat satu entri per jenis.',
            ], 422);
        }

        DB::transaction(function () use ($pendaftaran, $validated): void {
            foreach ($validated['nilai_tes'] as $row) {
                PmbNilaiTes::query()->updateOrCreate(
                    [
                        'id_pendaftaran' => $pendaftaran->id,
                        'id_jenis_tes' => $row['id_jenis_tes'],
                    ],
                    [
                        'nilai' => $row['nilai'],
                        'status' => 'success',
                    ]
                );
            }

            $scores = collect($validated['nilai_tes'])->pluck('nilai');
            $rata = $scores->isEmpty() ? null : (int) round($scores->avg());

            PmbHasilSeleksi::query()->updateOrCreate(
                ['id_pendaftaran' => $pendaftaran->id],
                [
                    'status' => $validated['status_seleksi'],
                    'nilai' => $rata,
                    'peringkat' => null,
                    'keterangan' => $validated['keterangan'] ?? null,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai tes dan hasil seleksi berhasil disimpan.',
            'data' => $this->buildFormData($pendaftaran),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormData(PmbPendaftaran $pendaftaran): array
    {
        $jenisTes = $this->activeJenisTesQuery()->get(['id', 'nama', 'keterangan', 'is_wajib']);

        $nilaiTes = PmbNilaiTes::query()
            ->where('id_pendaftaran', $pendaftaran->id)
            ->whereIn('id_jenis_tes', $jenisTes->pluck('id'))
            ->get(['id', 'id_jenis_tes', 'nilai', 'status', 'keterangan']);

        $hasil = PmbHasilSeleksi::query()
            ->where('id_pendaftaran', $pendaftaran->id)
            ->first(['id', 'status', 'keterangan', 'nilai', 'peringkat']);

        return [
            'pendaftaran' => [
                'id' => $pendaftaran->id,
                'no_pendaftaran' => $pendaftaran->no_pendaftaran,
            ],
            'jenis_tes' => $jenisTes,
            'nilai_tes' => $nilaiTes,
            'hasil_seleksi' => $hasil,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<PmbJenisTes>
     */
    private function activeJenisTesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return PmbJenisTes::query()
            ->where(function ($q): void {
                $q->where('is_aktif', true)->orWhereNull('is_aktif');
            })
            ->orderBy('nama');
    }
}
