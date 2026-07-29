<?php

namespace App\Livewire\Dosen;

use App\Models\Agama;
use App\Models\Dosen;
use App\Models\Kota;
use App\Models\Negara;
use App\Models\Provinsi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Profil extends Component
{
    use WithFileUploads;

    public string $activeTab = 'biodata';

    public int $dosenId;

    public string $kode_dosen = '';

    public string $nip = '';

    public string $nidn = '';

    public string $nama = '';

    public string $email = '';

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

    public string $id_negara = '';

    public string $id_provinsi = '';

    public string $id_kota = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    /** @var TemporaryUploadedFile|null */
    public $foto_upload = null;

    public ?string $foto = null;

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();

        $this->dosenId = $dosen->id;
        $this->kode_dosen = (string) $dosen->kode_dosen;
        $this->nip = (string) $dosen->nip;
        $this->nidn = (string) $dosen->nidn;
        $this->nama = $dosen->nama;
        $this->email = (string) $dosen->email;
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
        $this->id_negara = (string) $dosen->id_negara;
        $this->id_provinsi = (string) $dosen->id_provinsi;
        $this->id_kota = (string) $dosen->id_kota;
        $this->foto = $dosen->foto;
    }

    /**
     * Subset dari DosenController::updateMyProfile — hanya field kontak/biodata & gelar yang
     * boleh diubah lewat self-service; kode_dosen/nip/nidn sengaja hanya ditampilkan (read-only).
     */
    public function saveProfil(): void
    {
        $dosen = Dosen::findOrFail($this->dosenId);

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('dosen', 'email')->ignore($dosen->id)],
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
        ]);

        foreach ([
            'email', 'gelar_depan', 'gelar_belakang', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'status_perkawinan', 'kewarganegaraan', 'no_hp', 'alamat', 'kode_pos', 'id_kota', 'id_provinsi', 'id_negara',
        ] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $dosen->update($validated);

        session()->flash('status', 'Profil berhasil diperbarui.');
    }

    /**
     * Sama dengan DosenController::updateMyPassword.
     */
    public function savePassword(): void
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
            'new_password_confirmation' => ['required', 'string', 'same:new_password'],
        ], [], [
            'current_password' => 'password saat ini',
            'new_password' => 'password baru',
            'new_password_confirmation' => 'konfirmasi password baru',
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini tidak sesuai.');

            return;
        }

        $user->update(['password' => Hash::make($this->new_password)]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        session()->flash('status', 'Password berhasil diubah.');
    }

    /**
     * Sama dengan DosenController::uploadMyFoto.
     */
    public function saveFoto(): void
    {
        $this->validate([
            'foto_upload' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ]);

        $dosen = Dosen::findOrFail($this->dosenId);

        if ($dosen->foto) {
            Storage::disk('public')->delete($dosen->foto);
        }

        $filename = 'dosen_'.$dosen->id.'_'.time().'.'.$this->foto_upload->getClientOriginalExtension();
        $path = $this->foto_upload->storeAs('dosen/foto', $filename, 'public');

        $dosen->update(['foto' => $path]);

        $this->foto = $path;
        $this->foto_upload = null;

        session()->flash('status', 'Foto berhasil diperbarui.');
    }

    public function render()
    {
        return view('livewire.dosen.profil', [
            'agamaOptions' => Agama::orderBy('nama')->get(['id', 'nama']),
            'negaraOptions' => Negara::orderBy('nama')->get(['id', 'nama']),
            'provinsiOptions' => Provinsi::orderBy('nama')->get(['id', 'nama']),
            'kotaOptions' => Kota::orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.dosen');
    }
}
