<?php

namespace App\Livewire\Admin\Tagihan;

use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
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

    private function subquerySumPembayaranDisetujui(): string
    {
        return '(SELECT COALESCE(SUM(pembayaran.nominal), 0) FROM pembayaran WHERE pembayaran.id_tagihan = tagihan.id AND pembayaran.deleted_at IS NULL AND pembayaran.approved_at IS NOT NULL)';
    }

    /**
     * Sama persis dengan TagihanController::statusPembayaranAcc.
     *
     * @return string lunas|dibayar_sebagian|belum_bayar|kedaluwarsa
     */
    public function statusPembayaranAcc(Tagihan $tagihan, float $totalDisetujui): string
    {
        $totalTagihan = (float) $tagihan->total;
        if ($totalDisetujui + 0.009 >= $totalTagihan) {
            return 'lunas';
        }
        if ($totalDisetujui > 0) {
            return 'dibayar_sebagian';
        }
        if ($tagihan->status === 'expired') {
            return 'kedaluwarsa';
        }

        return 'belum_bayar';
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
        return [
            'lunas' => 'Lunas (disetujui penuh)',
            'dibayar_sebagian' => 'Dibayar sebagian (ACC)',
            'belum_bayar' => 'Belum ada pembayaran disetujui',
            'kedaluwarsa' => 'Kedaluwarsa (belum lunas)',
        ];
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
     * Sama persis dengan TagihanController::destroy.
     */
    public function delete(): void
    {
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

        $sumSub = $this->subquerySumPembayaranDisetujui();
        if ($this->filterStatusPembayaranAcc === 'lunas') {
            $query->whereRaw("{$sumSub} >= tagihan.total");
        } elseif ($this->filterStatusPembayaranAcc === 'dibayar_sebagian') {
            $query->whereRaw("{$sumSub} > 0")->whereRaw("{$sumSub} < tagihan.total");
        } elseif ($this->filterStatusPembayaranAcc === 'belum_bayar') {
            $query->whereRaw("{$sumSub} <= 0")->where('tagihan.status', '!=', 'expired');
        } elseif ($this->filterStatusPembayaranAcc === 'kedaluwarsa') {
            $query->where('tagihan.status', 'expired')->whereRaw("{$sumSub} < tagihan.total");
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

        $paymentSummaries = $tagihanList->getCollection()->mapWithKeys(function (Tagihan $tagihan) use ($sums) {
            $totalDisetujui = (float) ($sums[$tagihan->id] ?? 0);
            $totalTagihan = (float) $tagihan->total;

            return [$tagihan->id => [
                'total_disetujui' => $totalDisetujui,
                'sisa' => max(0, $totalTagihan - $totalDisetujui),
                'status' => $this->statusPembayaranAcc($tagihan, $totalDisetujui),
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
