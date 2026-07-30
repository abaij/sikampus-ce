<?php

namespace App\Livewire\Mahasiswa\Survey;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Isi extends Component
{
    #[Locked]
    public int $mahasiswaId;

    #[Locked]
    public int $surveyId;

    #[Url(as: 'krs')]
    public string $idKrs = '';

    #[Locked]
    public int $krsId;

    /** @var array<int, array{nilai_numerik: int|null, nilai_text: string|null}> */
    public array $responses = [];

    public string $feedback = '';

    public function mount(int $id): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $survey = Survey::whereNull('deleted_at')->find($id);
        abort_if($survey === null || ! $survey->is_active, 404, 'Survey tidak ditemukan.');
        $this->surveyId = $id;

        $krsId = (int) $this->idKrs;
        abort_if($krsId <= 0, 404, 'Parameter survey atau KRS tidak valid.');

        $krs = Krs::with('kelas.semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->find($krsId);

        abort_if($krs === null || ! $krs->kelas->semester || $krs->kelas->semester->id !== $survey->id_semester, 404, 'Mata kuliah tidak ditemukan.');
        $this->krsId = $krsId;

        $existing = $this->existingResponse;
        if ($existing) {
            $this->feedback = (string) ($existing->feedback ?? '');
            foreach ($existing->details as $detail) {
                $this->responses[$detail->id_survey_question] = [
                    'nilai_numerik' => $detail->nilai_numerik,
                    'nilai_text' => $detail->nilai_text,
                ];
            }
        }
    }

    #[Computed]
    public function survey(): Survey
    {
        return Survey::findOrFail($this->surveyId);
    }

    #[Computed]
    public function krs(): Krs
    {
        return Krs::with('kelas.kurikulumMatkul.matkul')->findOrFail($this->krsId);
    }

    /**
     * Sama persis dengan SurveyQuestionController::getBySurvey.
     */
    #[Computed]
    public function questions()
    {
        return SurveyQuestion::with('options')
            ->where('id_survey', $this->surveyId)
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function existingResponse(): ?SurveyResponse
    {
        return SurveyResponse::with('details')
            ->where('id_survey', $this->surveyId)
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->where('id_krs', $this->krsId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function setNumerik(int $questionId, int $value): void
    {
        $this->responses[$questionId]['nilai_numerik'] = $value;
    }

    public function setText(int $questionId, string $value): void
    {
        $this->responses[$questionId]['nilai_text'] = $value;
    }

    /**
     * Sama persis dengan SurveyController::submitSurveyResponse.
     */
    public function submit()
    {
        $unanswered = $this->questions->filter(function (SurveyQuestion $q) {
            $r = $this->responses[$q->id] ?? null;
            if ($q->tipe === 'essay') {
                return ! isset($r['nilai_text']) || trim((string) $r['nilai_text']) === '';
            }

            return ! isset($r['nilai_numerik']) || $r['nilai_numerik'] === null;
        });

        if ($unanswered->isNotEmpty()) {
            $this->addError('responses', "Silakan jawab semua pertanyaan. Masih ada {$unanswered->count()} pertanyaan yang belum dijawab.");

            return;
        }

        $existing = $this->existingResponse;

        if ($existing) {
            $existing->update([
                'tanggal_submit' => now(),
                'feedback' => trim($this->feedback) !== '' ? $this->feedback : null,
            ]);
            $response = $existing;
            $response->details()->delete();
        } else {
            $response = SurveyResponse::create([
                'id_survey' => $this->surveyId,
                'id_mahasiswa' => $this->mahasiswaId,
                'id_krs' => $this->krsId,
                'tanggal_submit' => now(),
                'feedback' => trim($this->feedback) !== '' ? $this->feedback : null,
            ]);
        }

        foreach ($this->questions as $q) {
            $r = $this->responses[$q->id] ?? [];
            $response->details()->create([
                'id_survey_question' => $q->id,
                // Kolom nilai_numerik NOT NULL dengan default 0 (bukan nullable) — controller API
                // legacy (SurveyController::submitSurveyResponse) mengirim null literal di sini
                // untuk pertanyaan essay, yang gagal di bawah sql_mode STRICT_TRANS_TABLES
                // (config('database.connections.mysql.strict') = true). 0 dipakai supaya
                // pertanyaan essay benar-benar bisa disimpan.
                'nilai_numerik' => $r['nilai_numerik'] ?? 0,
                'nilai_text' => $r['nilai_text'] ?? null,
            ]);
        }

        session()->flash('status', 'Survey berhasil disimpan.');

        return redirect()->route('mahasiswa.survey');
    }

    public function render()
    {
        return view('livewire.mahasiswa.survey.isi')->extends('layouts.mahasiswa');
    }
}
