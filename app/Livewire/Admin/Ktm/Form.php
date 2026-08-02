<?php

namespace App\Livewire\Admin\Ktm;

use App\Models\Ktm;
use App\Models\Mahasiswa;
use App\Models\Setting;
use App\Services\KtmImageGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

class Form extends Component
{
    use WithFileUploads;

    public const SETTING_KEY_KTM_TEMPLATE = Index::SETTING_KEY_KTM_TEMPLATE;

    public ?int $ktmId = null;

    public ?int $id_mahasiswa = null;

    public string $mahasiswaLabel = '';

    public string $mahasiswaSearch = '';

    public string $nomor_ktm = '';

    public string $status = 'active';

    public $file = null;

    public function mount(?int $id = null): void
    {
        $this->ktmId = $id;

        if ($id === null) {
            return;
        }

        $ktm = Ktm::with('mahasiswa')->findOrFail($id);
        $this->authorizeScope($ktm);

        $this->id_mahasiswa = $ktm->id_mahasiswa;
        $this->mahasiswaLabel = trim(($ktm->mahasiswa->nim ?? '').' - '.($ktm->mahasiswa->nama ?? ''));
        $this->nomor_ktm = (string) $ktm->nomor_ktm;
        $this->status = $ktm->status ?? 'active';
    }

    protected function authorizeScope(Ktm $ktm): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $ktm->mahasiswa?->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }
    }

    /**
     * Sama dengan KtmController::getMahasiswaOptions (only_without_ktm=1) — cari-lalu-pilih,
     * bukan memuat semua mahasiswa sekaligus.
     */
    #[Computed]
    public function mahasiswaResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        $idsWithKtm = Ktm::query()->whereNull('deleted_at')->pluck('id_mahasiswa');

        $query = Mahasiswa::query()
            ->select('id', 'nim', 'nama', 'id_prodi')
            ->whereNull('deleted_at')
            ->when($idsWithKtm->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $idsWithKtm));

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        $s = $this->mahasiswaSearch;
        $query->where(fn ($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%"));

        return $query->orderBy('nim')->limit(20)->get();
    }

    public function selectMahasiswa(int $id, string $label): void
    {
        $this->id_mahasiswa = $id;
        $this->mahasiswaLabel = $label;
        $this->mahasiswaSearch = '';
    }

    public function clearMahasiswa(): void
    {
        $this->id_mahasiswa = null;
        $this->mahasiswaLabel = '';
    }

    /**
     * Sama dengan KtmController::store (mode tambah) / KtmController::update (mode ubah).
     */
    public function save()
    {
        $success = $this->ktmId === null ? $this->saveCreate() : $this->saveUpdate();

        if (! $success) {
            return;
        }

        session()->flash('status', 'Data KTM berhasil disimpan.');

        return redirect()->route('admin.administrasi.ktm');
    }

    protected function saveCreate(): bool
    {
        $validated = $this->validate([
            'id_mahasiswa' => [
                'required',
                'integer',
                Rule::exists('mahasiswa', 'id')->whereNull('deleted_at'),
                Rule::unique('ktm', 'id_mahasiswa')->whereNull('deleted_at'),
            ],
            'nomor_ktm' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ], [
            'id_mahasiswa.unique' => 'Mahasiswa ini sudah memiliki data KTM.',
        ]);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            $mhsCheck = Mahasiswa::whereNull('deleted_at')->find((int) $validated['id_mahasiswa']);
            if ($allowedProdiIds !== null && (! $mhsCheck || ! in_array((int) $mhsCheck->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }

        $templatePath = $this->currentTemplatePath();
        if (! $templatePath || ! Storage::disk('public')->exists($templatePath)) {
            $this->addError('id_mahasiswa', 'Template KTM belum diatur. Unggah template gambar di tab Template terlebih dahulu.');

            return false;
        }

        $mhs = Mahasiswa::query()
            ->whereKey((int) $validated['id_mahasiswa'])
            ->with(['prodi.jenjang'])
            ->firstOrFail();

        try {
            $filePath = KtmImageGenerator::makeDefault()->generateToStorage($mhs, $templatePath);
        } catch (RuntimeException $e) {
            $this->addError('id_mahasiswa', $e->getMessage());

            return false;
        }

        $actor = $this->actorName();

        Ktm::create([
            'id_mahasiswa' => (int) $validated['id_mahasiswa'],
            'nomor_ktm' => $validated['nomor_ktm'] ?: null,
            'file' => $filePath,
            'status' => $validated['status'] ?: 'active',
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return true;
    }

    protected function saveUpdate(): bool
    {
        $ktm = Ktm::with('mahasiswa')->findOrFail($this->ktmId);
        $this->authorizeScope($ktm);

        $validated = $this->validate([
            'nomor_ktm' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpeg,jpg,png,webp'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $update = ['updated_by' => $this->actorName()];
        $update['nomor_ktm'] = $validated['nomor_ktm'] ?: null;
        if (! empty($validated['status'])) {
            $update['status'] = $validated['status'];
        }

        if ($this->file) {
            if ($ktm->file) {
                Storage::disk('public')->delete($ktm->file);
            }
            $update['file'] = $this->file->store('ktm', 'public');
        }

        $ktm->update($update);

        return true;
    }

    protected function currentTemplatePath(): ?string
    {
        return Setting::query()->where('key', self::SETTING_KEY_KTM_TEMPLATE)->value('value');
    }

    protected function actorName(): string
    {
        $user = Auth::user();

        return $user ? ((string) ($user->name ?? $user->id)) : 'system';
    }

    public function render()
    {
        return view('livewire.admin.ktm.form')->extends('layouts.web');
    }
}
