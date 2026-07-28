<?php

namespace App\Livewire\Admin\AturanAksesKeuangan;

use App\Models\AturanAksesKeuangan;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $aturanAksesKeuanganId = null;

    public string $kode_akses = '';

    public string $nama = '';

    // Terikat <input type="number">, bukan <select> — tetap string karena input kosong
    // mengirim "" yang tidak bisa dikonversi PHP ke properti typed float. Lihat SKILL.md.
    public string $persentase_minimum = '';

    public string $status = 'active';

    // Otomatis huruf kecil saat diketik — kenyamanan UI meniru perilaku input di frontend,
    // validasi regex huruf kecil tetap berlaku sama seperti di controller.
    public function updatedKodeAkses(): void
    {
        $this->kode_akses = strtolower($this->kode_akses);
    }

    public function mount(?int $id = null): void
    {
        $this->aturanAksesKeuanganId = $id;

        if ($id === null) {
            return;
        }

        $aturanAksesKeuangan = AturanAksesKeuangan::findOrFail($id);

        $this->kode_akses = $aturanAksesKeuangan->kode_akses;
        $this->nama = (string) $aturanAksesKeuangan->nama;
        $this->persentase_minimum = $aturanAksesKeuangan->persentase_minimum !== null
            ? (string) $aturanAksesKeuangan->persentase_minimum
            : '';
        $this->status = $aturanAksesKeuangan->status ?? 'active';
    }

    /**
     * Sama persis dengan AturanAksesKeuanganController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('aturan_akses_keuangan', 'kode_akses');
        if ($this->aturanAksesKeuanganId) {
            $uniqueKode = $uniqueKode->ignore($this->aturanAksesKeuanganId);
        }

        return [
            'kode_akses' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', $uniqueKode],
            'nama' => ['nullable', 'string', 'max:255'],
            'persentase_minimum' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'kode_akses.required' => 'Kode akses wajib diisi.',
            'kode_akses.regex' => 'Kode akses hanya huruf kecil, angka, dan underscore.',
            'kode_akses.unique' => 'Kode akses sudah digunakan.',
            'persentase_minimum.min' => 'Persentase minimal tidak boleh negatif.',
            'persentase_minimum.max' => 'Persentase maksimal 100.',
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['nama'] === '') {
            $validated['nama'] = null;
        }

        if ($validated['persentase_minimum'] === '') {
            $validated['persentase_minimum'] = null;
        }

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        if ($this->aturanAksesKeuanganId) {
            AturanAksesKeuangan::findOrFail($this->aturanAksesKeuanganId)->update($validated);
        } else {
            AturanAksesKeuangan::create($validated);
        }

        session()->flash('status', 'Aturan akses keuangan berhasil disimpan.');

        return redirect()->route('admin.keuangan.aturan-akses-keuangan');
    }

    public function render()
    {
        return view('livewire.admin.aturan-akses-keuangan.form')->extends('layouts.web');
    }
}
