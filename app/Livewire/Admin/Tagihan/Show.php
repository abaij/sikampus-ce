<?php

namespace App\Livewire\Admin\Tagihan;

use App\Livewire\Admin\Tagihan\Concerns\ForwardsIndexState;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $tagihanId;

    public Tagihan $tagihan;

    public float $totalPembayaranDisetujui;

    public float $sisaPembayaranDisetujui;

    public string $statusPembayaranAcc;

    /**
     * Sama persis dengan TagihanController::show.
     */
    public function mount(int $id): void
    {
        $this->tagihanId = $id;

        $tagihan = Tagihan::with([
            'mahasiswa.prodi',
            'semester',
            'tagihanRinci.komponenBiaya',
            'pembayaran' => function ($q) {
                $q->whereNotNull('approved_at')
                    ->whereNull('deleted_at')
                    ->orderByDesc('tanggal_pembayaran')
                    ->orderByDesc('id');
            },
        ])->findOrFail($id);

        $this->ensureAccess($tagihan);

        $this->tagihan = $tagihan;

        $totalDisetujui = (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
        $totalTagihan = (float) $tagihan->total;

        $this->totalPembayaranDisetujui = $totalDisetujui;
        $this->sisaPembayaranDisetujui = max(0, $totalTagihan - $totalDisetujui);
        $this->statusPembayaranAcc = $this->resolveStatusPembayaranAcc($tagihan, $totalDisetujui);

        $this->resolveBackUrl();
    }

    /**
     * Sama persis dengan TagihanController::assertTagihanInUserScope.
     */
    private function ensureAccess(Tagihan $tagihan): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasScopeRestriction()) {
            return;
        }

        $allowed = $user->getAllowedProdiIds();
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
     * Sama persis dengan TagihanController::statusPembayaranAcc.
     *
     * @return string lunas|dibayar_sebagian|belum_bayar|kedaluwarsa
     */
    private function resolveStatusPembayaranAcc(Tagihan $tagihan, float $totalDisetujui): string
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

    public function render()
    {
        return view('livewire.admin.tagihan.show')->extends('layouts.web');
    }
}
