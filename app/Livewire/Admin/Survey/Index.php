<?php

namespace App\Livewire\Admin\Survey;

use App\Models\Survey;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail/ubah (lihat Survey\Concerns\ForwardsIndexState).
    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?bool — lihat catatan di SKILL.md.
    #[Url(as: 'is_active')]
    public string $filterActive = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActive(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus survey.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan SurveyController::destroy.
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'survey', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus survey.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        Survey::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan SurveyController::index.
     */
    public function render()
    {
        $query = Survey::with('semester');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterActive !== '') {
            $query->where('is_active', $this->filterActive === '1');
        }

        $surveyList = $query->orderByDesc('created_at')->paginate($this->perPage);

        // Diselipkan ke link "Lihat"/"Ubah" supaya tombol Kembali di halaman detail/ubah bisa
        // mendarat di halaman/filter yang sama persis — lihat Survey\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'is_active' => $this->filterActive !== '' ? $this->filterActive : null,
            'page' => $surveyList->currentPage() > 1 ? $surveyList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.survey.index', [
            'surveyList' => $surveyList,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
