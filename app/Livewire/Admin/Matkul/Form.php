<?php

namespace App\Livewire\Admin\Matkul;

use App\Livewire\Admin\Matkul\Concerns\ForwardsIndexState;
use App\Models\JenisMatkul;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $matkulId = null;

    public string $kode = '';

    public string $nama = '';

    public string $nama_en = '';

    public string $deskripsi = '';

    // Terikat <input type="number">, bukan <select> — tetap string (bukan ?int) karena input
    // kosong mengirim "" yang tidak bisa dikonversi PHP ke int typed property. Lihat SKILL.md.
    public string $sks = '';

    public string $semester = '';

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    public ?int $id_prodi = null;

    public ?int $id_jenis_matkul = null;

    public string $status = 'active';

    public function mount(?int $id = null): void
    {
        $this->matkulId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $matkul = Matkul::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }

        $this->kode = $matkul->kode;
        $this->nama = $matkul->nama;
        $this->nama_en = (string) $matkul->nama_en;
        $this->deskripsi = (string) $matkul->deskripsi;
        $this->sks = $matkul->sks !== null ? (string) $matkul->sks : '';
        $this->semester = $matkul->semester !== null ? (string) $matkul->semester : '';
        $this->id_prodi = $matkul->id_prodi;
        $this->id_jenis_matkul = $matkul->id_jenis_matkul;
        $this->status = $matkul->status ?? 'active';
    }

    /**
     * Rule sama persis dengan MatkulController::store/update (kode unik per prodi).
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('matkul', 'kode')->where('id_prodi', $this->id_prodi);

        if ($this->matkulId) {
            $uniqueKode = $uniqueKode->ignore($this->matkulId);
        }

        return [
            'kode' => ['required', 'string', 'max:50', $uniqueKode],
            'nama' => ['required', 'string', 'max:255'],
            'nama_en' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'sks' => ['nullable', 'integer', 'min:1', 'max:10'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_jenis_matkul' => ['nullable', 'integer', 'exists:jenis_matkul,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['nama_en', 'deskripsi', 'sks', 'semester'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }
        if ($validated['sks'] !== null) {
            $validated['sks'] = (int) $validated['sks'];
        }
        if ($validated['semester'] !== null) {
            $validated['semester'] = (int) $validated['semester'];
        }

        $user = Auth::user();

        if ($this->matkulId) {
            $matkul = Matkul::findOrFail($this->matkulId);

            if ($user && $user->hasScopeRestriction()) {
                $allowedProdiIds = $user->getAllowedProdiIds();
                if ($allowedProdiIds !== null && ! in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
                }
                if ($allowedProdiIds !== null && $validated['id_prodi'] !== null && ! in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke program studi ini.');
                }
            }

            // Sama persis dengan MatkulController::update — sinkronkan kurikulum_matkul milik
            // kelas pada semester aktif supaya nama/SKS yang tampil di kelas tidak basi.
            DB::transaction(function () use ($matkul, $validated) {
                $matkul->update($validated);

                $activeSemester = Semester::where('is_active', true)->first();

                if ($activeSemester) {
                    $kurikulumMatkulIds = DB::table('kelas')
                        ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
                        ->where('kelas.id_semester', $activeSemester->id)
                        ->where('kurikulum_matkul.id_matkul', $matkul->id)
                        ->whereNull('kelas.deleted_at')
                        ->whereNull('kurikulum_matkul.deleted_at')
                        ->pluck('kurikulum_matkul.id')
                        ->unique()
                        ->toArray();

                    if (! empty($kurikulumMatkulIds)) {
                        KurikulumMatkul::whereIn('id', $kurikulumMatkulIds)->update([
                            'kode_matkul' => $matkul->kode,
                            'nama_matkul' => $matkul->nama,
                            'nama_matkul_en' => $matkul->nama_en,
                            'sks' => $matkul->sks,
                        ]);
                    }
                }
            });
        } else {
            Matkul::create($validated);
        }

        session()->flash('status', 'Mata kuliah berhasil disimpan.');

        return redirect($this->backUrl);
    }

    public function render()
    {
        $user = Auth::user();
        $prodiQuery = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $prodiQuery->whereIn('id', $allowedProdiIds);
            }
        }
        $prodiOptions = $prodiQuery->orderBy('nama')->get()->map(fn ($p) => (object) [
            'id' => $p->id,
            'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
        ]);

        $jenisMatkulOptions = JenisMatkul::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama', 'kode'])
            ->map(fn ($j) => (object) [
                'id' => $j->id,
                'label' => $j->kode ? "{$j->nama} ({$j->kode})" : $j->nama,
            ]);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.matkul.form', [
            'prodiOptions' => $prodiOptions,
            'jenisMatkulOptions' => $jenisMatkulOptions,
        ])->extends('layouts.web');
    }
}
