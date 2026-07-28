<?php

namespace App\Livewire\Admin\Wisuda;

use App\Models\Wisuda;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Form extends Component
{
    public ?int $wisudaId = null;

    public string $nama = '';

    public string $tanggal_wisuda = '';

    public string $keterangan = '';

    // Terikat <x-searchable-select> (entangle), jadi aman sebagai string biasa.
    public string $status = 'inactive';

    public function mount(?int $id = null): void
    {
        $this->wisudaId = $id;

        if ($id === null) {
            return;
        }

        $wisuda = Wisuda::findOrFail($id);

        $this->nama = $wisuda->nama;
        $this->tanggal_wisuda = $wisuda->tanggal_wisuda?->format('Y-m-d') ?? '';
        $this->keterangan = (string) $wisuda->keterangan;
        $this->status = $wisuda->status ?? 'inactive';
    }

    public function statusOptions(): array
    {
        return [
            'inactive' => 'Tidak aktif',
            'active' => 'Aktif',
        ];
    }

    /**
     * Sama persis dengan WisudaController::store/update.
     */
    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_wisuda' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'tanggal_wisuda' => 'tanggal wisuda',
        ];
    }

    private function actor(): string
    {
        $user = Auth::user();

        return $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
    }

    public function save()
    {
        $validated = $this->validate();

        $validated['nama'] = trim($validated['nama']);
        if (($validated['status'] ?? '') === '') {
            $validated['status'] = 'inactive';
        }
        if ($validated['keterangan'] === '') {
            $validated['keterangan'] = null;
        }

        // Sama persis dengan pemeriksaan duplikat di controller: satu nama + tanggal hanya boleh sekali.
        $duplicate = Wisuda::query()
            ->where('nama', $validated['nama'])
            ->whereDate('tanggal_wisuda', $validated['tanggal_wisuda'])
            ->when($this->wisudaId, fn ($q) => $q->where('id', '!=', $this->wisudaId))
            ->exists();

        if ($duplicate) {
            $this->addError('nama', $this->wisudaId
                ? 'Sudah ada wisuda lain dengan nama dan tanggal wisuda yang sama.'
                : 'Sudah ada wisuda dengan nama dan tanggal wisuda yang sama.');

            return null;
        }

        $actor = $this->actor();

        if ($this->wisudaId) {
            $validated['updated_by'] = $actor;
            Wisuda::findOrFail($this->wisudaId)->update($validated);
        } else {
            $validated['created_by'] = $actor;
            $validated['updated_by'] = $actor;
            Wisuda::create($validated);
        }

        session()->flash('status', 'Data wisuda berhasil disimpan.');

        return redirect()->route('admin.akademik.wisuda');
    }

    public function render()
    {
        return view('livewire.admin.wisuda.form')->extends('layouts.web');
    }
}
