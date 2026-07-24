<?php

namespace App\Livewire\Admin\Fakultas;

use App\Models\Dosen;
use App\Models\Fakultas;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $fakultasId = null;

    public string $nama = '';

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

    public ?int $id_dekan = null;

    public string $status = 'active';

    public function mount(?int $id = null): void
    {
        $this->fakultasId = $id;

        if ($id === null) {
            $user = Auth::user();
            if ($user && $user->hasFakultasScope()) {
                abort(403, 'Anda hanya dapat mengakses fakultas dalam scope Anda. Pembuatan fakultas baru tidak diizinkan.');
            }

            return;
        }

        $fakultas = Fakultas::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (! empty($fakultasScopeIds) && ! in_array((int) $fakultas->id, $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        $this->nama = $fakultas->nama;
        $this->kode = (string) $fakultas->kode;
        $this->deskripsi = (string) $fakultas->deskripsi;
        $this->website = (string) $fakultas->website;
        $this->email = (string) $fakultas->email;
        $this->telepon = (string) $fakultas->telepon;
        $this->alamat = (string) $fakultas->alamat;
        $this->kota = (string) $fakultas->kota;
        $this->provinsi = (string) $fakultas->provinsi;
        $this->kode_pos = (string) $fakultas->kode_pos;
        $this->negara = (string) $fakultas->negara;
        $this->id_dekan = $fakultas->id_dekan;
        $this->status = $fakultas->status ?? 'active';
    }

    /**
     * Rule sama persis dengan FakultasController::store/update.
     */
    protected function rules(): array
    {
        $uniqueNama = Rule::unique('fakultas', 'nama');
        $uniqueKode = Rule::unique('fakultas', 'kode');

        if ($this->fakultasId) {
            $uniqueNama = $uniqueNama->ignore($this->fakultasId);
            $uniqueKode = $uniqueKode->ignore($this->fakultasId);
        }

        return [
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
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
            'id_dekan' => ['nullable', 'integer', 'exists:dosen,id'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();
        $validated['status'] = ($validated['status'] ?? null) === 'inactive' ? 'inactive' : 'active';

        // Buang string kosong jadi null untuk kolom nullable, konsisten dengan input form kosong.
        foreach (['kode', 'deskripsi', 'website', 'email', 'telepon', 'alamat', 'kota', 'provinsi', 'kode_pos', 'negara'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->fakultasId) {
            $fakultas = Fakultas::findOrFail($this->fakultasId);

            $user = Auth::user();
            if ($user && $user->hasFakultasScope()) {
                $fakultasScopeIds = $user->getFakultasScopeIds();
                if (! empty($fakultasScopeIds) && ! in_array((int) $fakultas->id, $fakultasScopeIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
                }
            }

            $fakultas->update($validated);
        } else {
            $user = Auth::user();
            if ($user && $user->hasFakultasScope()) {
                abort(403, 'Anda hanya dapat mengakses fakultas dalam scope Anda. Pembuatan fakultas baru tidak diizinkan.');
            }

            Fakultas::create($validated);
        }

        session()->flash('status', 'Fakultas berhasil disimpan.');

        return redirect()->route('admin.fakultas.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di Index::render().
        return view('livewire.admin.fakultas.form', [
            'dosenOptions' => Dosen::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
