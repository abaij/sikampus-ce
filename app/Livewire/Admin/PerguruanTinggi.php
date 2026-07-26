<?php

namespace App\Livewire\Admin;

use App\Models\Kota;
use App\Models\Provinsi;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class PerguruanTinggi extends Component
{
    use WithFileUploads;

    public string $nama = '';

    public string $alamat = '';

    public string $yayasan = '';

    public ?int $id_provinsi = null;

    public ?int $id_kota = null;

    public string $kodePos = '';

    public string $telepon = '';

    public string $email = '';

    public string $website = '';

    public string $logo = '';

    public $logoUpload = null;

    /**
     * Data perguruan tinggi disimpan sebagai baris key-value di tabel settings
     * (prefix app_univ_*), sama seperti yang dibaca PerguruanTinggiPage di frontend
     * lewat GET /settings/prefix/app_univ.
     */
    public function mount(): void
    {
        $settings = Setting::where('key', 'like', 'app_univ_%')->pluck('value', 'key');

        $this->nama = (string) ($settings['app_univ_name'] ?? '');
        $this->alamat = (string) ($settings['app_univ_address'] ?? '');
        $this->yayasan = (string) ($settings['app_univ_yayasan'] ?? '');
        $this->kodePos = (string) ($settings['app_univ_postal_code'] ?? '');
        $this->telepon = (string) ($settings['app_univ_phone'] ?? '');
        $this->email = (string) ($settings['app_univ_email'] ?? '');
        $this->website = (string) ($settings['app_univ_website'] ?? '');
        $this->logo = (string) ($settings['app_univ_logo'] ?? '');

        // Provinsi/kota disimpan sebagai teks nama (bukan id) di settings, jadi dicocokkan
        // balik ke master data supaya dropdown-nya terisi — sama seperti matchNamaLokasi di frontend.
        $provinsiNama = trim((string) ($settings['app_univ_province'] ?? ''));
        $kotaNama = trim((string) ($settings['app_univ_city'] ?? ''));

        if ($provinsiNama !== '') {
            $provinsi = Provinsi::whereRaw('LOWER(nama) = ?', [strtolower($provinsiNama)])->first();
            $this->id_provinsi = $provinsi?->id;

            if ($provinsi && $kotaNama !== '') {
                $kota = Kota::where('id_provinsi', $provinsi->id)
                    ->whereRaw('LOWER(nama) = ?', [strtolower($kotaNama)])
                    ->first();
                $this->id_kota = $kota?->id;
            }
        }
    }

    public function updatedIdProvinsi(): void
    {
        $this->id_kota = null;
        unset($this->kotaOptions);
    }

    #[Computed]
    public function kotaOptions()
    {
        if (! $this->id_provinsi) {
            return collect();
        }

        return Kota::where('id_provinsi', $this->id_provinsi)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Sama persis dengan SettingController::uploadFile — validasi, nama file, dan folder 'logos'.
     */
    public function updatedLogoUpload(): void
    {
        $this->validateOnly('logoUpload', [
            'logoUpload' => ['image', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp'],
        ]);

        $filename = time().'_'.uniqid().'.'.$this->logoUpload->getClientOriginalExtension();
        $path = $this->logoUpload->storeAs('logos', $filename, 'public');

        $this->logo = config('app.url').'/storage/'.$path;
        $this->logoUpload = null;
    }

    public function removeLogo(): void
    {
        $this->logo = '';
    }

    /**
     * Sama persis dengan SettingController::bulkUpdate: updateOrCreate per key.
     */
    public function save(): void
    {
        $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'yayasan' => ['nullable', 'string', 'max:255'],
            'id_provinsi' => ['nullable', 'integer', 'exists:provinsi,id'],
            'id_kota' => ['nullable', 'integer', 'exists:kota,id'],
            'kodePos' => ['nullable', 'string', 'max:20'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $provinsiNama = $this->id_provinsi ? Provinsi::find($this->id_provinsi)?->nama : '';
        $kotaNama = $this->id_kota ? Kota::find($this->id_kota)?->nama : '';

        $settings = [
            'app_univ_name' => $this->nama,
            'app_univ_address' => $this->alamat,
            'app_univ_city' => (string) $kotaNama,
            'app_univ_province' => (string) $provinsiNama,
            'app_univ_postal_code' => $this->kodePos,
            'app_univ_phone' => $this->telepon,
            'app_univ_email' => $this->email,
            'app_univ_website' => $this->website,
            'app_univ_logo' => $this->logo,
            'app_univ_yayasan' => $this->yayasan,
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        session()->flash('status', 'Data perguruan tinggi berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.admin.perguruan-tinggi', [
            'provinsiOptions' => Provinsi::orderBy('nama')->get(['id', 'nama']),
            'kotaOptions' => $this->kotaOptions,
        ])->extends('layouts.web');
    }
}
