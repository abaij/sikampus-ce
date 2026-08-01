<?php

namespace App\Livewire\Admin\Survey;

use App\Livewire\Admin\Survey\Concerns\ForwardsIndexState;
use App\Models\Prodi;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyResponseDetail;
use App\Support\PanelAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $surveyId;

    public string $activeTab = 'detail';

    // Tab Pertanyaan
    public array $expandedQuestions = [];

    public bool $showQuestionModal = false;

    public ?int $editingQuestionId = null;

    public string $qPertanyaan = '';

    public string $qTipe = 'essay';

    public array $qOptions = [];

    public ?int $confirmingQuestionDeleteId = null;

    // Tab Statistik — string, bukan ?int, karena diikat ke <select> (lihat catatan SKILL.md).
    public string $filterProdi = '';

    public string $sortBy = 'nilai';

    public string $sortOrder = 'desc';

    public function mount(int $id): void
    {
        $this->surveyId = $id;

        Survey::findOrFail($id);

        $this->resolveBackUrl();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function toggleQuestion(int $id): void
    {
        if (in_array($id, $this->expandedQuestions, true)) {
            $this->expandedQuestions = array_values(array_diff($this->expandedQuestions, [$id]));
        } else {
            $this->expandedQuestions[] = $id;
        }
    }

    #[Computed]
    public function survey(): Survey
    {
        return Survey::with('semester')->findOrFail($this->surveyId);
    }

    /**
     * Sama dengan SurveyQuestionController::index — di-scope ke survey ini.
     */
    #[Computed]
    public function questions()
    {
        return SurveyQuestion::where('id_survey', $this->surveyId)
            ->with('options')
            ->orderBy('id')
            ->get();
    }

    public function updatedQTipe(string $value): void
    {
        if ($value === 'essay') {
            $this->qOptions = [];
        }
    }

    public function addOption(): void
    {
        $this->qOptions[] = ['id' => null, 'opsi' => '', 'nilai_numerik' => null, 'urutan' => count($this->qOptions)];
    }

    public function removeOption(int $index): void
    {
        unset($this->qOptions[$index]);
        $this->qOptions = array_values($this->qOptions);
    }

    public function openAddQuestion(): void
    {
        // Tombol-tombol pemicu manajemen pertanyaan disembunyikan di Blade untuk user tanpa hak
        // ubah/hapus, tapi method Livewire tetap bisa dipanggil langsung lewat request yang
        // dipalsukan — pengecekan di sini (dan di openEditQuestion/saveQuestion/
        // confirmDeleteQuestion/deleteQuestion) adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah survey.');

        $this->resetValidation();
        $this->editingQuestionId = null;
        $this->qPertanyaan = '';
        $this->qTipe = 'essay';
        $this->qOptions = [];
        $this->showQuestionModal = true;
    }

    public function openEditQuestion(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah survey.');

        $this->resetValidation();

        $question = SurveyQuestion::with('options')->findOrFail($id);

        $this->editingQuestionId = $question->id;
        $this->qPertanyaan = $question->pertanyaan;
        $this->qTipe = $question->tipe ?? 'essay';
        $this->qOptions = $question->options->map(fn (SurveyQuestionOption $o) => [
            'id' => $o->id,
            'opsi' => $o->opsi,
            'nilai_numerik' => $o->nilai_numerik,
            'urutan' => $o->urutan,
        ])->all();
        $this->showQuestionModal = true;
    }

    public function closeQuestionModal(): void
    {
        $this->showQuestionModal = false;
        $this->editingQuestionId = null;
    }

    /**
     * Sama dengan SurveyQuestionController::store/update — opsi hanya dipakai untuk tipe
     * selain essay, dan kombinasi (opsi, nilai_numerik) per pertanyaan harus unik.
     */
    protected function questionRules(): array
    {
        return [
            'qPertanyaan' => ['required', 'string'],
            'qTipe' => ['nullable', 'string', 'in:likert,essay,multiple_choice,single_choice'],
            'qOptions' => ['nullable', 'array'],
            'qOptions.*.opsi' => ['required_with:qOptions', 'string'],
            'qOptions.*.nilai_numerik' => ['nullable', 'integer'],
            'qOptions.*.urutan' => ['nullable', 'integer'],
        ];
    }

    public function saveQuestion(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah survey.');

        $this->validate($this->questionRules());

        if ($this->qTipe === 'essay') {
            $this->qOptions = [];
        }

        try {
            DB::beginTransaction();

            if ($this->editingQuestionId) {
                $question = SurveyQuestion::findOrFail($this->editingQuestionId);
                $question->update([
                    'pertanyaan' => $this->qPertanyaan,
                    'tipe' => $this->qTipe,
                ]);

                $optionIds = collect($this->qOptions)->pluck('id')->filter()->all();
                $question->options()->whereNotIn('id', $optionIds !== [] ? $optionIds : [0])->delete();
            } else {
                $question = SurveyQuestion::create([
                    'id_survey' => $this->surveyId,
                    'pertanyaan' => $this->qPertanyaan,
                    'tipe' => $this->qTipe,
                ]);
            }

            foreach ($this->qOptions as $index => $option) {
                $payload = [
                    'opsi' => $option['opsi'],
                    'nilai_numerik' => $option['nilai_numerik'] !== '' && $option['nilai_numerik'] !== null ? (int) $option['nilai_numerik'] : null,
                    'urutan' => $option['urutan'] ?? $index,
                ];

                if (! empty($option['id'])) {
                    SurveyQuestionOption::find($option['id'])?->update($payload);
                } else {
                    SurveyQuestionOption::create($payload + ['id_survey_question' => $question->id]);
                }
            }

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();

            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'survey_question_option_unique')) {
                $this->addError('qOptions', 'Opsi dengan kombinasi opsi dan nilai numerik yang sama sudah ada untuk pertanyaan ini.');

                return;
            }

            throw $e;
        }

        unset($this->questions);
        $this->closeQuestionModal();
        session()->flash('status', 'Pertanyaan survey berhasil disimpan.');
    }

    public function confirmDeleteQuestion(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus pertanyaan survey.');

        $this->confirmingQuestionDeleteId = $id;
    }

    public function cancelDeleteQuestion(): void
    {
        $this->confirmingQuestionDeleteId = null;
    }

    public function deleteQuestion(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus pertanyaan survey.');

        if (! $this->confirmingQuestionDeleteId) {
            return;
        }

        SurveyQuestion::findOrFail($this->confirmingQuestionDeleteId)->delete();

        $this->confirmingQuestionDeleteId = null;
        unset($this->questions);
        session()->flash('status', 'Pertanyaan survey dihapus.');
    }

    public function updatingFilterProdi(): void
    {
        unset($this->statistik);
    }

    public function updatingSortBy(): void
    {
        unset($this->statistik);
    }

    public function updatingSortOrder(): void
    {
        unset($this->statistik);
    }

    #[Computed]
    public function prodiOptions()
    {
        return Prodi::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama', 'kode']);
    }

    /**
     * Sama persis dengan SurveyController::getStatistik.
     */
    #[Computed]
    public function statistik(): array
    {
        $prodiId = $this->filterProdi !== '' ? (int) $this->filterProdi : null;

        $queryResponse = DB::table('survey_response')
            ->where('id_survey', $this->surveyId)
            ->whereNull('survey_response.deleted_at');

        if ($prodiId) {
            $queryResponse->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                ->where('mahasiswa.id_prodi', $prodiId)
                ->whereNull('mahasiswa.deleted_at');
        }

        $totalResponden = $queryResponse->distinct('survey_response.id_mahasiswa')->count('survey_response.id_mahasiswa');

        $questions = SurveyQuestion::where('id_survey', $this->surveyId)
            ->whereNull('deleted_at')
            ->with(['options' => fn ($q) => $q->whereNull('deleted_at')->orderBy('urutan')])
            ->orderBy('id')
            ->get();

        $statistikPertanyaan = [];
        foreach ($questions as $question) {
            $queryDetail = SurveyResponseDetail::where('survey_response_detail.id_survey_question', $question->id)
                ->whereNull('survey_response_detail.deleted_at')
                ->join('survey_response', 'survey_response_detail.id_survey_response', '=', 'survey_response.id')
                ->where('survey_response.id_survey', $this->surveyId)
                ->whereNull('survey_response.deleted_at');

            if ($prodiId) {
                $queryDetail->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                    ->where('mahasiswa.id_prodi', $prodiId)
                    ->whereNull('mahasiswa.deleted_at');
            }

            $totalJawaban = $queryDetail->count();

            $avgNilai = null;
            if ($question->tipe !== 'essay') {
                $avgNilai = $queryDetail->avg('survey_response_detail.nilai_numerik');
                $avgNilai = $avgNilai ? round((float) $avgNilai, 2) : null;
            }

            $distribusiJawaban = [];
            if ($question->tipe !== 'essay' && $question->options) {
                foreach ($question->options as $option) {
                    $countQuery = SurveyResponseDetail::where('survey_response_detail.id_survey_question', $question->id)
                        ->where('survey_response_detail.nilai_numerik', $option->nilai_numerik)
                        ->whereNull('survey_response_detail.deleted_at')
                        ->join('survey_response', 'survey_response_detail.id_survey_response', '=', 'survey_response.id')
                        ->where('survey_response.id_survey', $this->surveyId)
                        ->whereNull('survey_response.deleted_at');

                    if ($prodiId) {
                        $countQuery->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                            ->where('mahasiswa.id_prodi', $prodiId)
                            ->whereNull('mahasiswa.deleted_at');
                    }

                    $count = $countQuery->count();

                    $distribusiJawaban[] = [
                        'opsi' => $option->opsi,
                        'nilai_numerik' => $option->nilai_numerik,
                        'jumlah' => $count,
                        'persentase' => $totalJawaban > 0 ? round(($count / $totalJawaban) * 100, 2) : 0,
                    ];
                }
            }

            $statistikPertanyaan[] = [
                'id' => $question->id,
                'pertanyaan' => $question->pertanyaan,
                'tipe' => $question->tipe,
                'total_jawaban' => $totalJawaban,
                'rata_rata_nilai' => $avgNilai,
                'distribusi_jawaban' => $distribusiJawaban,
            ];
        }

        if ($this->sortBy === 'nilai') {
            usort($statistikPertanyaan, function ($a, $b) {
                $nilaiA = $a['rata_rata_nilai'] ?? 0;
                $nilaiB = $b['rata_rata_nilai'] ?? 0;

                return $this->sortOrder === 'desc' ? $nilaiB <=> $nilaiA : $nilaiA <=> $nilaiB;
            });
        } else {
            usort($statistikPertanyaan, function ($a, $b) {
                return $this->sortOrder === 'desc'
                    ? strcmp($b['pertanyaan'], $a['pertanyaan'])
                    : strcmp($a['pertanyaan'], $b['pertanyaan']);
            });
        }

        return [
            'total_responden' => $totalResponden,
            'pertanyaan' => $statistikPertanyaan,
        ];
    }

    public function render()
    {
        return view('livewire.admin.survey.show')->extends('layouts.web');
    }
}
