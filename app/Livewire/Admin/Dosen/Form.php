<?php

namespace App\Livewire\Admin\Dosen;

use App\Livewire\Admin\Dosen\Concerns\ForwardsIndexState;
use App\Models\Agama;
use App\Models\Dosen;
use App\Models\Kota;
use App\Models\Negara;
use App\Models\Provinsi;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $dosenId = null;

    public string $nama = '';

    public string $kode_dosen = '';

    public string $email = '';

    public string $nip = '';

    public string $nidn = '';

    public string $nidk = '';

    public string $nupn = '';

    public string $gelar_depan = '';

    public string $gelar_belakang = '';

    public string $tempat_lahir = '';

    public string $tanggal_lahir = '';

    public string $jenis_kelamin = '';

    public ?int $agama = null;

    public string $status_perkawinan = '';

    public string $kewarganegaraan = '';

    public string $no_hp = '';

    public string $alamat = '';

    public string $kode_pos = '';

    public string $id_provinsi = '';

    public string $id_kota = '';

    public string $id_negara = '';

    // Default sama dengan default kolom di migration (add_columns_to_dosen_table) — kolomnya
    // NOT NULL dengan default DB 0; lihat catatan yang sama di App\Livewire\Admin\Prodi\Form.
    public int $kuota_bimbingan_akademik = 0;

    public int $kuota_bimbingan_ta = 0;

    public function mount(?int $id = null): void
    {
        $this->dosenId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $dosen = Dosen::findOrFail($id);

        $this->nama = $dosen->nama;
        $this->kode_dosen = (string) $dosen->kode_dosen;
        $this->email = (string) $dosen->email;
        $this->nip = (string) $dosen->nip;
        $this->nidn = (string) $dosen->nidn;
        $this->nidk = (string) $dosen->nidk;
        $this->nupn = (string) $dosen->nupn;
        $this->gelar_depan = (string) $dosen->gelar_depan;
        $this->gelar_belakang = (string) $dosen->gelar_belakang;
        $this->tempat_lahir = (string) $dosen->tempat_lahir;
        $this->tanggal_lahir = $dosen->tanggal_lahir?->format('Y-m-d') ?? '';
        $this->jenis_kelamin = (string) $dosen->jenis_kelamin;
        $this->agama = $dosen->agama;
        $this->status_perkawinan = (string) $dosen->status_perkawinan;
        $this->kewarganegaraan = (string) $dosen->kewarganegaraan;
        $this->no_hp = (string) $dosen->no_hp;
        $this->alamat = (string) $dosen->alamat;
        $this->kode_pos = (string) $dosen->kode_pos;
        $this->id_provinsi = (string) $dosen->id_provinsi;
        $this->id_kota = (string) $dosen->id_kota;
        $this->id_negara = (string) $dosen->id_negara;
        $this->kuota_bimbingan_akademik = $dosen->kuota_bimbingan_akademik ?? 0;
        $this->kuota_bimbingan_ta = $dosen->kuota_bimbingan_ta ?? 0;
    }

    /**
     * Rule sama persis dengan DosenController::store/update.
     */
    protected function rules(): array
    {
        $uniqueEmail = Rule::unique('dosen', 'email');
        $uniqueKode = Rule::unique('dosen', 'kode_dosen');
        $uniqueNip = Rule::unique('dosen', 'nip');
        $uniqueNidn = Rule::unique('dosen', 'nidn');

        if ($this->dosenId) {
            $uniqueEmail = $uniqueEmail->ignore($this->dosenId);
            $uniqueKode = $uniqueKode->ignore($this->dosenId);
            $uniqueNip = $uniqueNip->ignore($this->dosenId);
            $uniqueNidn = $uniqueNidn->ignore($this->dosenId);
        }

        return [
            'nama' => $this->dosenId ? ['sometimes', 'required', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', $uniqueEmail],
            'kode_dosen' => ['nullable', 'string', 'max:50', $uniqueKode],
            'nip' => ['nullable', 'string', 'max:50', $uniqueNip],
            'nidn' => ['nullable', 'string', 'max:50', $uniqueNidn],
            'nidk' => ['nullable', 'string', 'max:50'],
            'nupn' => ['nullable', 'string', 'max:50'],
            'gelar_depan' => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P'])],
            'agama' => ['nullable', 'integer'],
            'status_perkawinan' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'id_kota' => ['nullable', 'string', 'max:50'],
            'id_provinsi' => ['nullable', 'string', 'max:50'],
            'id_negara' => ['nullable', 'string', 'max:50'],
            'kuota_bimbingan_akademik' => ['nullable', 'integer', 'min:0'],
            'kuota_bimbingan_ta' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach ([
            'email', 'kode_dosen', 'nip', 'nidn', 'nidk', 'nupn', 'gelar_depan', 'gelar_belakang',
            'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'status_perkawinan', 'kewarganegaraan',
            'no_hp', 'alamat', 'kode_pos', 'id_kota', 'id_provinsi', 'id_negara',
        ] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->dosenId) {
            Dosen::findOrFail($this->dosenId)->update($validated);
        } else {
            Dosen::create($validated);
        }

        session()->flash('status', 'Dosen berhasil disimpan.');

        return redirect($this->backUrl);
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.dosen.form', [
            'agamaOptions' => Agama::orderBy('nama')->get(['id', 'nama']),
            'provinsiOptions' => Provinsi::orderBy('nama')->get(['id', 'nama']),
            'kotaOptions' => Kota::orderBy('nama')->get(['id', 'nama']),
            'negaraOptions' => Negara::orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
