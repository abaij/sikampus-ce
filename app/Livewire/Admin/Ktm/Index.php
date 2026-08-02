<?php

namespace App\Livewire\Admin\Ktm;

use App\Models\Ktm;
use App\Models\Mahasiswa;
use App\Models\Setting;
use App\Services\KtmImageGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use Throwable;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public const SETTING_KEY_KTM_TEMPLATE = 'ktm_template';

    public string $activeTab = 'data';

    public string $search = '';

    public string $filterStatus = '';

    public int $perPage = 15;

    public ?int $confirmingDeleteId = null;

    public ?int $confirmingRegenerateId = null;

    public $templateFile = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama dengan KtmController::destroy.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $ktm = Ktm::with('mahasiswa')->findOrFail($this->confirmingDeleteId);
        $this->authorizeScope($ktm);

        $actor = $this->actorName();
        if ($ktm->file) {
            Storage::disk('public')->delete($ktm->file);
        }
        $ktm->update(['deleted_by' => $actor]);
        $ktm->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
        session()->flash('status', 'Data KTM dihapus.');
    }

    public function confirmRegenerate(int $id): void
    {
        $this->confirmingRegenerateId = $id;
    }

    public function cancelRegenerate(): void
    {
        $this->confirmingRegenerateId = null;
    }

    /**
     * Sama dengan KtmController::regenerate.
     */
    public function regenerate(): void
    {
        if (! $this->confirmingRegenerateId) {
            return;
        }

        $ktm = Ktm::with('mahasiswa')->findOrFail($this->confirmingRegenerateId);
        $this->authorizeScope($ktm);

        $templatePath = $this->currentTemplatePath();
        if (! $templatePath || ! Storage::disk('public')->exists($templatePath)) {
            $this->confirmingRegenerateId = null;
            session()->flash('ktm_error', 'Template KTM belum diatur. Unggah template gambar di tab Template terlebih dahulu.');

            return;
        }

        $mhs = Mahasiswa::query()->whereKey($ktm->id_mahasiswa)->with(['prodi.jenjang'])->firstOrFail();
        $previousFile = $ktm->file;

        try {
            $filePath = KtmImageGenerator::makeDefault()->generateToStorage($mhs, $templatePath);
        } catch (RuntimeException $e) {
            $this->confirmingRegenerateId = null;
            session()->flash('ktm_error', $e->getMessage());

            return;
        }

        if ($previousFile && $previousFile !== $filePath) {
            Storage::disk('public')->delete($previousFile);
        }

        $ktm->update(['file' => $filePath, 'updated_by' => $this->actorName()]);

        $this->confirmingRegenerateId = null;
        session()->flash('status', 'Gambar KTM berhasil dibuat ulang.');
    }

    /**
     * Sama dengan KtmController::storeSettingTemplate — dipicu otomatis begitu file terpilih.
     */
    public function updatedTemplateFile(): void
    {
        $this->resetValidation();

        $this->validate([
            'templateFile' => ['required', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,gif,webp'],
        ], [
            'templateFile.required' => 'Pilih file gambar terlebih dahulu.',
            'templateFile.image' => 'File harus berupa gambar.',
            'templateFile.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $row = Setting::withTrashed()->where('key', self::SETTING_KEY_KTM_TEMPLATE)->first();
        if ($row?->trashed()) {
            $row->restore();
        }

        if ($row && $row->value) {
            Storage::disk('public')->delete($row->value);
        }

        $tw = (int) config('ktm.template_width', 800);
        $th = (int) config('ktm.template_height', 457);
        if ($tw < 1 || $th < 1) {
            $this->addError('templateFile', 'Konfigurasi ukuran template KTM tidak valid.');
            $this->reset('templateFile');

            return;
        }

        try {
            $image = ImageManager::gd()->read($this->templateFile->getRealPath());
            $image->cover($tw, $th, 'center');
        } catch (Throwable) {
            $this->addError('templateFile', 'Gagal memproses gambar. Pastikan berkas gambar valid.');
            $this->reset('templateFile');

            return;
        }

        $path = 'ktm/templates/tpl_'.Str::uuid()->toString().'.png';
        $absolute = Storage::disk('public')->path($path);
        $dir = dirname($absolute);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->addError('templateFile', 'Tidak dapat menyimpan template.');
            $this->reset('templateFile');

            return;
        }

        try {
            $image->save($absolute);
        } catch (Throwable) {
            $this->addError('templateFile', 'Gagal menyimpan gambar template.');
            $this->reset('templateFile');

            return;
        }

        if ($row) {
            $row->update(['value' => $path]);
        } else {
            Setting::create([
                'key' => self::SETTING_KEY_KTM_TEMPLATE,
                'value' => $path,
                'description' => 'Template gambar KTM (admin)',
                'order' => 0,
            ]);
        }

        $this->reset('templateFile');
        unset($this->currentTemplateUrl);
        session()->flash('status', 'Template KTM berhasil disimpan.');
    }

    #[Computed]
    public function currentTemplateUrl(): ?string
    {
        $path = $this->currentTemplatePath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    protected function currentTemplatePath(): ?string
    {
        return Setting::query()->where('key', self::SETTING_KEY_KTM_TEMPLATE)->value('value');
    }

    #[Computed]
    public function statusOptions()
    {
        return ['active' => 'Aktif', 'inactive' => 'Nonaktif'];
    }

    protected function actorName(): string
    {
        $user = Auth::user();

        return $user ? ((string) ($user->name ?? $user->id)) : 'system';
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
     * Sama persis dengan KtmController::index.
     */
    public function render()
    {
        $query = Ktm::query()->with(['mahasiswa' => function ($q) {
            $q->select('id', 'nim', 'nama', 'id_prodi')->with(['prodi:id,nama,kode']);
        }]);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('mahasiswa', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
            }
        }

        if ($this->search !== '') {
            $s = $this->search;
            $query->whereHas('mahasiswa', function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%");
            });
        }

        if ($this->filterStatus !== '') {
            $query->where('ktm.status', $this->filterStatus);
        }

        $ktmList = $query->orderByDesc('ktm.id')->paginate($this->perPage);

        return view('livewire.admin.ktm.index', [
            'ktmList' => $ktmList,
        ])->extends('layouts.web');
    }
}
