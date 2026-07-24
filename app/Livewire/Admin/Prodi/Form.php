<?php

namespace App\Livewire\Admin\Prodi;

use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Jenjang;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $prodiId = null;

    public string $nama = '';

    public string $nama_en = '';

    public string $kode = '';

    public string $deskripsi = '';

    public string $website = '';

    public string $email = '';

    public string $telepon = '';

    public string $alamat = '';

    public string $kota = '';

    public string $provinsi = '';

    public string $kode_pos = '';

    public string $negara = '';

    public ?int $id_fakultas = null;

    public ?int $id_kaprodi = null;

    public ?int $id_sekprodi = null;

    public ?int $id_jenjang = null;

    public ?int $id_semester_aktif = null;

    // Default sama dengan default kolom di migration (add_columns_to_prodi_table) — kolomnya
    // NOT NULL dengan default DB, tapi default itu hanya berlaku kalau kolom di-OMIT dari INSERT,
    // bukan saat dikirim eksplisit null (yang terjadi kalau properti Livewire ini nullable).
    public int $sks_minimal = 0;

    public float $ipk_lulus_minimal = 0;

    public string $gelar = '';

    public string $gelar_singkat = '';

    public int $maks_dosen_pembimbing = 1;

    public int $maks_dosen_penguji = 1;

    public bool $is_pmb_open = true;

    public string $status = 'active';

    public function mount(?int $id = null): void
    {
        $this->prodiId = $id;

        if ($id === null) {
            return;
        }

        $prodi = Prodi::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $prodi->id, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        $this->nama = $prodi->nama;
        $this->nama_en = (string) $prodi->nama_en;
        $this->kode = (string) $prodi->kode;
        $this->deskripsi = (string) $prodi->deskripsi;
        $this->website = (string) $prodi->website;
        $this->email = (string) $prodi->email;
        $this->telepon = (string) $prodi->telepon;
        $this->alamat = (string) $prodi->alamat;
        $this->kota = (string) $prodi->kota;
        $this->provinsi = (string) $prodi->provinsi;
        $this->kode_pos = (string) $prodi->kode_pos;
        $this->negara = (string) $prodi->negara;
        $this->id_fakultas = $prodi->id_fakultas;
        $this->id_kaprodi = $prodi->id_kaprodi;
        $this->id_sekprodi = $prodi->id_sekprodi;
        $this->id_jenjang = $prodi->id_jenjang;
        $this->id_semester_aktif = $prodi->id_semester_aktif;
        $this->sks_minimal = $prodi->sks_minimal;
        $this->ipk_lulus_minimal = $prodi->ipk_lulus_minimal;
        $this->gelar = (string) $prodi->gelar;
        $this->gelar_singkat = (string) $prodi->gelar_singkat;
        $this->maks_dosen_pembimbing = $prodi->maks_dosen_pembimbing;
        $this->maks_dosen_penguji = $prodi->maks_dosen_penguji;
        $this->is_pmb_open = (bool) $prodi->is_pmb_open;
        $this->status = $prodi->status ?? 'active';
    }

    /**
     * Rule sama persis dengan ProdiController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('prodi', 'kode');
        $uniqueNama = Rule::unique('prodi', 'nama');

        if ($this->prodiId) {
            $uniqueKode = $uniqueKode->ignore($this->prodiId);
            $uniqueNama = $uniqueNama->ignore($this->prodiId);
        }

        return [
            'nama' => $this->prodiId ? ['sometimes', 'required', 'string', 'max:255', $uniqueNama] : ['required', 'string', 'max:255'],
            'nama_en' => ['nullable', 'string', 'max:255'],
            'kode' => ['nullable', 'string', 'max:50', $uniqueKode],
            'deskripsi' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'negara' => ['nullable', 'string', 'max:100'],
            'id_fakultas' => ['nullable', 'integer', 'exists:fakultas,id'],
            'id_kaprodi' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_sekprodi' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_jenjang' => ['nullable', 'integer', 'exists:jenjang,id'],
            'id_semester_aktif' => ['nullable', 'integer', 'exists:semester,id'],
            'sks_minimal' => ['nullable', 'integer', 'min:0'],
            'ipk_lulus_minimal' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'gelar' => ['nullable', 'string', 'max:100'],
            'gelar_singkat' => ['nullable', 'string', 'max:20'],
            'maks_dosen_pembimbing' => ['nullable', 'integer', 'min:1'],
            'maks_dosen_penguji' => ['nullable', 'integer', 'min:1'],
            'is_pmb_open' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['nama_en', 'kode', 'deskripsi', 'website', 'email', 'telepon', 'alamat', 'kota', 'provinsi', 'kode_pos', 'negara', 'gelar', 'gelar_singkat'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $user = Auth::user();

        if ($this->prodiId) {
            $prodi = Prodi::findOrFail($this->prodiId);

            if ($user && $user->hasScopeRestriction()) {
                $allowedProdiIds = $user->getAllowedProdiIds();
                if ($allowedProdiIds !== null && ! in_array((int) $prodi->id, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke program studi ini.');
                }
            }

            if ($user && $user->hasScopeRestriction() && isset($validated['id_fakultas']) && $validated['id_fakultas'] !== null) {
                $fakultasScopeIds = $user->getFakultasScopeIds();
                if (empty($fakultasScopeIds) || ! in_array((int) $validated['id_fakultas'], $fakultasScopeIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
                }
            }

            $prodi->update($validated);
        } else {
            if ($user && $user->hasScopeRestriction()) {
                $fakultasScopeIds = $user->getFakultasScopeIds();
                if (empty($fakultasScopeIds)) {
                    abort(403, 'Anda hanya dapat mengakses program studi dalam scope Anda. Pembuatan program studi baru tidak diizinkan.');
                }
                if (isset($validated['id_fakultas']) && $validated['id_fakultas'] !== null && ! in_array((int) $validated['id_fakultas'], $fakultasScopeIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
                }
            }

            Prodi::create($validated);
        }

        session()->flash('status', 'Program studi berhasil disimpan.');

        return redirect()->route('admin.prodi.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render().
        return view('livewire.admin.prodi.form', [
            'fakultasOptions' => Fakultas::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
            'dosenOptions' => Dosen::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
            'jenjangOptions' => Jenjang::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
            'semesterOptions' => Semester::whereNull('deleted_at')->orderBy('kode', 'desc')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
