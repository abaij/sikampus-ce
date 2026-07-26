<?php

namespace App\Livewire\Admin\JenisPenilaian;

use App\Models\JenisPenilaian;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $jenisPenilaianId = null;

    public string $kode = '';

    public string $nama = '';

    public int $bobot = 0;

    public string $status = 'manual';

    public function mount(?int $id = null): void
    {
        $this->jenisPenilaianId = $id;

        if ($id === null) {
            return;
        }

        $jenisPenilaian = JenisPenilaian::findOrFail($id);

        $this->kode = $jenisPenilaian->kode;
        $this->nama = $jenisPenilaian->nama;
        $this->bobot = (int) $jenisPenilaian->bobot;
        $this->status = $jenisPenilaian->status ?? 'manual';
    }

    /**
     * Rule sama persis dengan JenisPenilaianController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('jenis_penilaian', 'kode');

        if ($this->jenisPenilaianId) {
            $uniqueKode = $uniqueKode->ignore($this->jenisPenilaianId);
        }

        return [
            'kode' => ['required', 'string', 'max:50', $uniqueKode],
            'nama' => ['required', 'string', 'max:255'],
            'bobot' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['manual', 'otomatis'])],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        // Sama persis dengan JenisPenilaianController::store/update — total bobot seluruh
        // jenis penilaian (di luar baris yang sedang diedit) tidak boleh melebihi 100%.
        $totalBobotLain = JenisPenilaian::when(
            $this->jenisPenilaianId,
            fn ($q) => $q->where('id', '!=', $this->jenisPenilaianId)
        )->sum('bobot');
        $totalBobotBaru = $totalBobotLain + $validated['bobot'];

        if ($totalBobotBaru > 100) {
            $this->addError(
                'bobot',
                "Total bobot jenis penilaian lain: {$totalBobotLain}%. Dengan bobot baru ({$validated['bobot']}%), total menjadi {$totalBobotBaru}% yang melebihi batas maksimal 100%."
            );

            return;
        }

        if ($this->jenisPenilaianId) {
            JenisPenilaian::findOrFail($this->jenisPenilaianId)->update($validated);
        } else {
            JenisPenilaian::create($validated);
        }

        session()->flash('status', 'Jenis penilaian berhasil disimpan.');

        return redirect()->route('admin.akademik.jenis-penilaian');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jenis-penilaian.form', [
            'totalBobotLain' => JenisPenilaian::when(
                $this->jenisPenilaianId,
                fn ($q) => $q->where('id', '!=', $this->jenisPenilaianId)
            )->sum('bobot'),
        ])->extends('layouts.web');
    }
}
