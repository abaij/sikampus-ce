<?php

namespace App\Livewire\Admin\StrukturBiaya;

use App\Models\KategoriBiaya;
use App\Models\KomponenBiaya;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    public ?int $strukturBiayaId = null;

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    public ?int $id_kategori_biaya = null;

    public ?int $id_prodi = null;

    public ?int $id_angkatan = null;

    public ?int $id_periode = null;

    public ?int $id_komponen_biaya = null;

    // Terikat <input type="number">, bukan <select> — tetap string (bukan int) karena input
    // kosong mengirim "" yang tidak bisa dikonversi PHP ke properti typed int. Lihat SKILL.md.
    public string $tahap = '1';

    public string $nominal = '';

    public function mount(?int $id = null): void
    {
        $this->strukturBiayaId = $id;

        if ($id === null) {
            return;
        }

        $strukturBiaya = StrukturBiaya::findOrFail($id);

        $this->ensureAccess($strukturBiaya);

        $this->id_kategori_biaya = $strukturBiaya->id_kategori_biaya;
        $this->id_prodi = $strukturBiaya->id_prodi;
        $this->id_angkatan = $strukturBiaya->id_angkatan;
        $this->id_periode = $strukturBiaya->id_periode;
        $this->id_komponen_biaya = $strukturBiaya->id_komponen_biaya;
        $this->tahap = (string) ($strukturBiaya->tahap ?? 1);
        $this->nominal = (string) $strukturBiaya->nominal;
    }

    #[Computed]
    public function kategoriBiayaOptions(): array
    {
        return KategoriBiaya::orderBy('nama')->pluck('nama', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function prodiOptions(): array
    {
        $query = Prodi::query()->orderBy('nama');

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id', $allowedProdiIds);
            }
        }

        return $query->pluck('nama', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    #[Computed]
    public function komponenBiayaOptions(): array
    {
        return KomponenBiaya::orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($k) => [$k->id => "{$k->nama} ({$k->kode})"])
            ->all();
    }

    /**
     * Sama persis dengan StrukturBiayaController::store/update.
     */
    protected function rules(): array
    {
        return [
            'id_kategori_biaya' => ['nullable', 'integer', 'exists:kategori_biaya,id'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_angkatan' => ['required', 'integer', 'exists:semester,id'],
            'id_periode' => ['required', 'integer', 'exists:semester,id'],
            'id_komponen_biaya' => ['nullable', 'integer', 'exists:komponen_biaya,id'],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'nominal' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'id_angkatan.required' => 'Angkatan (semester masuk) harus dipilih.',
            'id_angkatan.exists' => 'Angkatan tidak valid.',
            'id_periode.required' => 'Periode berlaku harus dipilih.',
            'id_periode.exists' => 'Periode berlaku tidak valid.',
            'nominal.required' => 'Nominal harus diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
            'nominal.min' => 'Nominal tidak boleh negatif.',
        ];
    }

    private function ensureAccess(StrukturBiaya $strukturBiaya): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasScopeRestriction()) {
            return;
        }

        $allowedProdiIds = $user->getAllowedProdiIds();
        if ($allowedProdiIds !== null && (! $strukturBiaya->id_prodi || ! in_array((int) $strukturBiaya->id_prodi, $allowedProdiIds, true))) {
            abort(403, 'Anda tidak memiliki akses ke struktur biaya ini.');
        }
    }

    public function save()
    {
        // Tahap opsional dengan default 1 (sama seperti controller) — dikosongkan dulu di sini
        // (bukan sesudah validate()) supaya tidak lolos sebagai string kosong ke rule 'integer'.
        if (trim($this->tahap) === '') {
            $this->tahap = '1';
        }

        $validated = $this->validate();
        $validated['tahap'] = (int) $validated['tahap'];

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                if ($validated['id_prodi'] === null) {
                    $this->addError('id_prodi', 'Program studi wajib dipilih dan harus dalam lingkup akses Anda.');

                    return;
                }

                if (! in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke program studi ini.');
                }
            }
        }

        $exists = StrukturBiaya::where('id_kategori_biaya', $validated['id_kategori_biaya'])
            ->where('id_prodi', $validated['id_prodi'])
            ->where('id_angkatan', $validated['id_angkatan'])
            ->where('id_periode', $validated['id_periode'])
            ->where('id_komponen_biaya', $validated['id_komponen_biaya'])
            ->where('tahap', $validated['tahap'])
            ->when($this->strukturBiayaId, fn ($q) => $q->where('id', '!=', $this->strukturBiayaId))
            ->exists();

        if ($exists) {
            $this->addError('nominal', 'Struktur biaya dengan kombinasi kategori biaya, prodi, angkatan, periode, komponen biaya, dan tahap ini sudah ada.');

            return;
        }

        if ($this->strukturBiayaId) {
            StrukturBiaya::findOrFail($this->strukturBiayaId)->update($validated);
        } else {
            StrukturBiaya::create($validated);
        }

        session()->flash('status', 'Struktur biaya berhasil disimpan.');

        return redirect()->route('admin.keuangan.struktur-biaya');
    }

    public function render()
    {
        return view('livewire.admin.struktur-biaya.form')->extends('layouts.web');
    }
}
