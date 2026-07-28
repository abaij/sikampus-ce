<?php

namespace App\Livewire\Admin\Survey;

use App\Livewire\Admin\Survey\Concerns\ForwardsIndexState;
use App\Models\Semester;
use App\Models\Survey;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $surveyId = null;

    public string $nama = '';

    public string $kode = '';

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    public ?int $id_semester = null;

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public string $keterangan = '';

    public bool $is_active = false;

    public function mount(?int $id = null): void
    {
        $this->surveyId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $survey = Survey::findOrFail($id);

        $this->nama = $survey->nama;
        $this->kode = $survey->kode;
        $this->id_semester = $survey->id_semester;
        $this->tanggal_mulai = $survey->tanggal_mulai?->format('Y-m-d') ?? '';
        $this->tanggal_selesai = $survey->tanggal_selesai?->format('Y-m-d') ?? '';
        $this->keterangan = (string) $survey->keterangan;
        $this->is_active = $survey->is_active;
    }

    /**
     * Rule sama persis dengan SurveyController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('survey', 'kode');

        if ($this->surveyId) {
            $uniqueKode = $uniqueKode->ignore($this->surveyId);
        }

        return [
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['required', 'string', 'max:255', $uniqueKode],
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['tanggal_mulai', 'tanggal_selesai', 'keterangan'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->surveyId) {
            Survey::findOrFail($this->surveyId)->update($validated);
        } else {
            Survey::create($validated);
        }

        session()->flash('status', 'Survey berhasil disimpan.');

        return redirect()->to($this->backUrl);
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.survey.form', [
            // ->all() supaya <x-searchable-select> memakainya sebagai array asosiatif polos
            // (id => label), bukan lewat jalur Collection Eloquent yang mengharapkan objek.
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get()
                ->mapWithKeys(fn (Semester $s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
                ->all(),
        ])->extends('layouts.web');
    }
}
