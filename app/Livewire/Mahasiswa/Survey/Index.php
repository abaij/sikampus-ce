<?php

namespace App\Livewire\Mahasiswa\Survey;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public ?int $expandedSurveyId = null;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $first = $this->surveys[0] ?? null;
        $this->expandedSurveyId = $first['id'] ?? null;
    }

    public function toggle(int $surveyId): void
    {
        $this->expandedSurveyId = $this->expandedSurveyId === $surveyId ? null : $surveyId;
    }

    /**
     * Sama persis dengan SurveyController::getSurveyAktifForMahasiswa.
     */
    #[Computed]
    public function surveys(): array
    {
        $surveys = Survey::with('semester')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $krsList = Krs::with(['kelas.kurikulumMatkul.matkul', 'kelas.semester', 'kelas.prodi'])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->get();

        $existingResponses = SurveyResponse::where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('id_survey')
            ->map(fn ($rows) => $rows->pluck('id_krs')->filter()->all());

        $result = [];
        foreach ($surveys as $survey) {
            $krsForSurvey = $krsList->filter(fn (Krs $krs) => $krs->kelas->semester && $krs->kelas->semester->id === $survey->id_semester);

            if ($krsForSurvey->isEmpty()) {
                continue;
            }

            $mataKuliah = $krsForSurvey->map(function (Krs $krs) use ($survey, $existingResponses) {
                $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
                if (! $matkul) {
                    return null;
                }

                $sudahDiisi = isset($existingResponses[$survey->id]) && in_array($krs->id, $existingResponses[$survey->id], true);

                return [
                    'id_krs' => $krs->id,
                    'kode_matkul' => $matkul->kode ?? '-',
                    'nama_matkul' => $matkul->nama ?? '-',
                    'sks' => $matkul->sks ?? 0,
                    'nama_kelas' => $krs->kelas->nama ?? '-',
                    'prodi' => $krs->kelas->prodi,
                    'sudah_diisi' => $sudahDiisi,
                ];
            })->filter()->values();

            if ($mataKuliah->isEmpty()) {
                continue;
            }

            $result[] = [
                'id' => $survey->id,
                'nama' => $survey->nama,
                'kode' => $survey->kode,
                'keterangan' => $survey->keterangan,
                'tanggal_mulai' => $survey->tanggal_mulai,
                'tanggal_selesai' => $survey->tanggal_selesai,
                'semester' => $survey->semester,
                'mata_kuliah' => $mataKuliah,
            ];
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.mahasiswa.survey.index')->extends('layouts.mahasiswa');
    }
}
