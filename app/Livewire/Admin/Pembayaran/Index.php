<?php

namespace App\Livewire\Admin\Pembayaran;

use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
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

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'acc_status')]
    public string $filterAccStatus = '';

    #[Url(as: 'tanggal_pembayaran_dari')]
    public string $tanggalDari = '';

    #[Url(as: 'tanggal_pembayaran_sampai')]
    public string $tanggalSampai = '';

    public int $perPage = 10;

    /**
     * Default filter periode = semester aktif, sama seperti default
     * PembayaranController::buildPembayaranListQuery saat parameter id_semester tidak dikirim
     * sama sekali.
     */
    public function mount(): void
    {
        $this->filterSemester = (string) (Semester::where('is_active', true)->value('id') ?? '');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAccStatus(): void
    {
        $this->resetPage();
    }

    public function updatingTanggalDari(): void
    {
        $this->resetPage();
    }

    public function updatingTanggalSampai(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    #[Computed]
    public function prodiOptions(): array
    {
        return Prodi::orderBy('nama')->pluck('nama', 'id')->all();
    }

    public function accStatusOptions(): array
    {
        return [
            'acc' => 'Sudah ACC',
            'belum_acc' => 'Belum ACC',
        ];
    }

    /**
     * Sama persis dengan PembayaranController::buildPembayaranListQuery + index. Tidak ada
     * pembatasan scope prodi — controller aslinya juga tidak menerapkan scope untuk pembayaran.
     */
    public function render()
    {
        $query = Pembayaran::with(['tagihan.mahasiswa.prodi', 'tagihan.semester']);

        if ($this->filterAccStatus === 'acc') {
            $query->whereNotNull('approved_at');
        } elseif ($this->filterAccStatus === 'belum_acc') {
            $query->whereNull('approved_at');
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('no_pembayaran', 'like', "%{$search}%")
                    ->orWhereHas('tagihan.mahasiswa', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%");
                    });
            });
        }

        if ($this->filterProdi !== '') {
            $prodiId = (int) $this->filterProdi;
            $query->whereHas('tagihan.mahasiswa', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        // filterSemester kosong (sengaja dikosongkan user) berarti tampilkan semua periode —
        // beda dengan mount() yang mem-prefill dengan semester aktif.
        if ($this->filterSemester !== '') {
            $semesterId = (int) $this->filterSemester;
            $query->whereHas('tagihan', function ($q) use ($semesterId) {
                $q->where('id_semester', $semesterId);
            });
        }

        if ($this->tanggalDari !== '') {
            $query->whereDate('tanggal_pembayaran', '>=', $this->tanggalDari);
        }

        if ($this->tanggalSampai !== '') {
            $query->whereDate('tanggal_pembayaran', '<=', $this->tanggalSampai);
        }

        $pembayaranList = $query->orderByDesc('tanggal_pembayaran')
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        // Diselipkan ke link "Lihat" supaya tombol Kembali di halaman detail/ubah bisa mendarat
        // di halaman/filter yang sama persis — lihat Show::mount()/Form::mount().
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'acc_status' => $this->filterAccStatus !== '' ? $this->filterAccStatus : null,
            'tanggal_pembayaran_dari' => $this->tanggalDari !== '' ? $this->tanggalDari : null,
            'tanggal_pembayaran_sampai' => $this->tanggalSampai !== '' ? $this->tanggalSampai : null,
            'page' => $pembayaranList->currentPage() > 1 ? $pembayaranList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.pembayaran.index', [
            'pembayaranList' => $pembayaranList,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
