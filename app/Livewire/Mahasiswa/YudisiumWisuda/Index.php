<?php

namespace App\Livewire\Mahasiswa\YudisiumWisuda;

use App\Models\Mahasiswa;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $mahasiswaId;

    public bool $showDaftarModal = false;

    public string $daftarIdWisuda = '';

    /** @var TemporaryUploadedFile|null */
    public $fotoFile = null;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan WisudaController::getMyYudisiumWisuda.
     */
    #[Computed]
    public function yudisium(): ?Yudisium
    {
        return Yudisium::with('jenis_keluar:id,nama')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    #[Computed]
    public function wisudaMahasiswa(): ?WisudaMahasiswa
    {
        return WisudaMahasiswa::with('wisuda:id,nama,tanggal_wisuda,keterangan,status')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();
    }

    #[Computed]
    public function jadwalWisudaAktif()
    {
        return Wisuda::whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('tanggal_wisuda')
            ->get(['id', 'nama', 'tanggal_wisuda', 'keterangan', 'status']);
    }

    #[Computed]
    public function canUploadFoto(): bool
    {
        return (bool) $this->wisudaMahasiswa?->wisuda;
    }

    #[Computed]
    public function canDaftarWisuda(): bool
    {
        return $this->yudisium !== null
            && $this->wisudaMahasiswa === null
            && $this->jadwalWisudaAktif->isNotEmpty();
    }

    public function openDaftarModal(): void
    {
        $this->resetValidation();
        $this->daftarIdWisuda = (string) ($this->jadwalWisudaAktif->first()?->id ?? '');
        $this->showDaftarModal = true;
    }

    public function closeDaftarModal(): void
    {
        $this->showDaftarModal = false;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan WisudaController::daftarWisudaMahasiswa.
     */
    public function submitDaftar(): void
    {
        if ($this->yudisium === null) {
            $this->addError('daftarIdWisuda', 'Anda belum memiliki data yudisium, sehingga belum bisa mendaftar wisuda.');

            return;
        }

        if ($this->wisudaMahasiswa !== null) {
            $this->addError('daftarIdWisuda', 'Anda sudah terdaftar sebagai peserta wisuda.');

            return;
        }

        $validated = $this->validate([
            'daftarIdWisuda' => ['required', 'integer'],
        ], [], ['daftarIdWisuda' => 'jadwal wisuda']);

        $wisuda = Wisuda::whereKey((int) $validated['daftarIdWisuda'])
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->first();

        if (! $wisuda) {
            $this->addError('daftarIdWisuda', 'Jadwal wisuda tidak valid atau tidak aktif.');

            return;
        }

        $user = Auth::user();
        $by = $user->name ?? (string) ($user->email ?? $user->id);

        WisudaMahasiswa::create([
            'id_mahasiswa' => $this->mahasiswaId,
            'id_wisuda' => $wisuda->id,
            'status' => 'pending',
            'created_by' => $by,
            'updated_by' => $by,
        ]);

        $this->showDaftarModal = false;
        $this->resetValidation();
        unset($this->wisudaMahasiswa, $this->canUploadFoto, $this->canDaftarWisuda);
        session()->flash('status', 'Pendaftaran wisuda berhasil dikirim. Status Anda saat ini: pending.');
    }

    /**
     * Sama persis dengan WisudaController::uploadMyFotoWisuda.
     */
    public function uploadFoto(): void
    {
        $wisudaMahasiswa = $this->wisudaMahasiswa;

        if (! $wisudaMahasiswa || ! $wisudaMahasiswa->wisuda) {
            $this->addError('fotoFile', 'Jadwal wisuda belum tersedia. Upload foto belum dapat dilakukan.');

            return;
        }

        $this->validate([
            'fotoFile' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        if ($wisudaMahasiswa->foto) {
            Storage::disk('public')->delete($wisudaMahasiswa->foto);
        }

        $path = $this->fotoFile->store('wisuda/foto-mahasiswa', 'public');
        $user = Auth::user();

        $wisudaMahasiswa->foto = $path;
        $wisudaMahasiswa->updated_by = $user->name ?? (string) ($user->email ?? $user->id);
        $wisudaMahasiswa->save();

        $this->fotoFile = null;
        $this->resetValidation();
        unset($this->wisudaMahasiswa);
        session()->flash('status', 'Foto wisuda berhasil diunggah.');
    }

    public function render()
    {
        return view('livewire.mahasiswa.yudisium-wisuda.index')->extends('layouts.mahasiswa');
    }
}
