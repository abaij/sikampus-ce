<?php

namespace App\Livewire\Admin\Tagihan;

use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use App\Services\StatusPembayaranTagihan;
use App\Support\PanelAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail/ubah (lihat Concerns\ForwardsIndexState::resolveBackUrl()).
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'status_pembayaran_acc')]
    public string $filterStatusPembayaranAcc = '';

    #[Url(as: 'lewat_jatuh_tempo')]
    public bool $filterLewatJatuhTempo = false;

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatusPembayaranAcc(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLewatJatuhTempo(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int>|null null = tanpa batasan scope
     */
    private function allowedProdiIds(): ?array
    {
        $user = Auth::user();
        if (! $user || ! $user->hasScopeRestriction()) {
            return null;
        }

        return $user->getAllowedProdiIds();
    }

    private function applyProdiScope(Builder $query): void
    {
        $allowed = $this->allowedProdiIds();
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas('mahasiswa', function (Builder $q) use ($allowed): void {
            $q->whereIn('id_prodi', $allowed);
        });
    }

    /**
     * Sama persis dengan TagihanController::assertTagihanInUserScope.
     */
    private function ensureAccess(Tagihan $tagihan): void
    {
        $allowed = $this->allowedProdiIds();
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            abort(403, 'Tagihan tidak termasuk lingkup akses Anda.');
        }

        $prodiId = (int) ($tagihan->mahasiswa?->id_prodi ?? 0);
        if (! in_array($prodiId, $allowed, true)) {
            abort(403, 'Tagihan tidak termasuk lingkup akses Anda.');
        }
    }

    /**
     * @return string lunas|dibayar_sebagian|belum_bayar|kedaluwarsa
     */
    public function statusPembayaranAcc(Tagihan $tagihan, float $totalDisetujui, float $kreditKeringanan = 0.0): string
    {
        return StatusPembayaranTagihan::hitung($tagihan, $totalDisetujui, $kreditKeringanan);
    }

    #[Computed]
    public function prodiOptions(): array
    {
        $query = Prodi::query()->orderBy('nama');

        $allowed = $this->allowedProdiIds();
        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return $query->pluck('nama', 'id')->all();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    public function statusPembayaranAccOptions(): array
    {
        return StatusPembayaranTagihan::opsi();
    }

    public function confirmDelete(int $id): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'tagihan', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus tagihan.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan TagihanController::destroy.
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'tagihan', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus tagihan.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        $tagihan = Tagihan::with('mahasiswa')->findOrFail($this->confirmingDeleteId);
        $this->ensureAccess($tagihan);

        $tagihan->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan TagihanController::buildTagihanListQuery + index.
     */
    public function render()
    {
        $query = Tagihan::with(['mahasiswa.prodi', 'semester', 'tagihanRinci.komponenBiaya']);
        $this->applyProdiScope($query);

        if ($this->search !== '') {
            $search = $this->search;
            $query->whereHas('mahasiswa', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%");
            });
        }

        if ($this->filterProdi !== '') {
            $prodiId = (int) $this->filterProdi;
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        if ($this->filterLewatJatuhTempo) {
            $query->whereNotNull('tanggal_jatuh_tempo')
                ->whereDate('tanggal_jatuh_tempo', '<', Carbon::today());
        }

        // Filter memakai ekspresi yang sama dengan label barisnya.
        if (array_key_exists($this->filterStatusPembayaranAcc, StatusPembayaranTagihan::opsi())) {
            $query->whereRaw(StatusPembayaranTagihan::sqlEkspresi().' = ?', [$this->filterStatusPembayaranAcc]);
        }

        $tagihanList = $query->orderByDesc('tanggal_tagihan')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $ids = $tagihanList->getCollection()->pluck('id');
        $sums = $ids->isEmpty() ? collect() : Pembayaran::query()
            ->whereIn('id_tagihan', $ids->all())
            ->whereNull('deleted_at')
            ->whereNotNull('approved_at')
            ->groupBy('id_tagihan')
            ->selectRaw('id_tagihan, SUM(nominal) as total_disetujui')
            ->pluck('total_disetujui', 'id_tagihan');

        $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds($ids->all());

        $paymentSummaries = $tagihanList->getCollection()->mapWithKeys(function (Tagihan $tagihan) use ($sums, $kredit) {
            $totalDisetujui = (float) ($sums[$tagihan->id] ?? 0);
            $kreditBaris = (float) ($kredit[$tagihan->id] ?? 0);
            $totalTagihan = (float) $tagihan->total;

            return [$tagihan->id => [
                'total_disetujui' => $totalDisetujui,
                'kredit_keringanan' => $kreditBaris,
                'sisa' => max(0.0, $totalTagihan - $totalDisetujui - $kreditBaris),
                'status' => $this->statusPembayaranAcc($tagihan, $totalDisetujui, $kreditBaris),
            ]];
        });

        // Diselipkan ke link "Lihat"/"Ubah" supaya tombol Kembali di halaman detail/ubah bisa
        // mendarat di halaman/filter yang sama persis — lihat Show::mount()/Form::mount().
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'status_pembayaran_acc' => $this->filterStatusPembayaranAcc !== '' ? $this->filterStatusPembayaranAcc : null,
            'lewat_jatuh_tempo' => $this->filterLewatJatuhTempo ? '1' : null,
            'page' => $tagihanList->currentPage() > 1 ? $tagihanList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.tagihan.index', [
            'tagihanList' => $tagihanList,
            'paymentSummaries' => $paymentSummaries,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
