<?php

namespace App\Livewire\Admin\StatusAkademik;

use App\Models\StatusAkademik;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $statusAkademikId = null;

    public string $nama = '';

    public string $deskripsi = '';

    public function mount(?int $id = null): void
    {
        $this->statusAkademikId = $id;

        if ($id === null) {
            return;
        }

        $statusAkademik = StatusAkademik::findOrFail($id);

        $this->nama = $statusAkademik->nama;
        $this->deskripsi = (string) $statusAkademik->deskripsi;
    }

    /**
     * Rule sama persis dengan StatusAkademikController::store/update.
     */
    protected function rules(): array
    {
        $uniqueNama = Rule::unique('status_akademik', 'nama')->whereNull('deleted_at');

        if ($this->statusAkademikId) {
            $uniqueNama = $uniqueNama->ignore($this->statusAkademikId);
        }

        return [
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();
        $validated['nama'] = trim($validated['nama']);

        if ($validated['deskripsi'] === '') {
            $validated['deskripsi'] = null;
        }

        if ($this->statusAkademikId) {
            StatusAkademik::findOrFail($this->statusAkademikId)->update($validated);
        } else {
            // Kalau ada baris soft-deleted dengan nama yang sama, pulihkan itu alih-alih
            // membuat baris baru — sama persis dengan StatusAkademikController::store.
            $trashed = StatusAkademik::onlyTrashed()
                ->where('nama', $validated['nama'])
                ->orderBy('id')
                ->first();

            if ($trashed) {
                $trashed->restore();
                $trashed->forceFill(['deskripsi' => $validated['deskripsi']])->save();
            } else {
                StatusAkademik::create($validated);
            }
        }

        session()->flash('status', 'Status akademik berhasil disimpan.');

        return redirect()->route('admin.status-akademik.index');
    }

    public function render()
    {
        return view('livewire.admin.status-akademik.form')->extends('layouts.web');
    }
}
