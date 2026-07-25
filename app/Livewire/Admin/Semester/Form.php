<?php

namespace App\Livewire\Admin\Semester;

use App\Models\Semester;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $semesterId = null;

    public string $kode = '';

    public string $nama = '';

    public bool $is_active = false;

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public function mount(?int $id = null): void
    {
        $this->semesterId = $id;

        if ($id === null) {
            return;
        }

        $semester = Semester::findOrFail($id);

        $this->kode = $semester->kode;
        $this->nama = $semester->nama;
        $this->is_active = $semester->is_active;
        $this->tanggal_mulai = $semester->tanggal_mulai?->format('Y-m-d\TH:i') ?? '';
        $this->tanggal_selesai = $semester->tanggal_selesai?->format('Y-m-d\TH:i') ?? '';
    }

    /**
     * Rule sama persis dengan SemesterController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('semester', 'kode');

        if ($this->semesterId) {
            $uniqueKode = $uniqueKode->ignore($this->semesterId);
        }

        return [
            'kode' => ['required', 'string', 'max:50', $uniqueKode],
            'nama' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['tanggal_mulai', 'tanggal_selesai'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->semesterId) {
            $semester = Semester::findOrFail($this->semesterId);

            if ($validated['is_active'] && ! $semester->is_active) {
                Semester::where('is_active', true)->where('id', '!=', $semester->id)->update(['is_active' => false]);
            }

            $semester->update($validated);
        } else {
            if ($validated['is_active']) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }

            Semester::create($validated);
        }

        session()->flash('status', 'Semester berhasil disimpan.');

        return redirect()->route('admin.semester.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.semester.form')->extends('layouts.web');
    }
}
