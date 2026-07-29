<?php

namespace App\Livewire\Admin\Pembayaran;

use App\Livewire\Admin\Pembayaran\Concerns\ForwardsIndexState;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $pembayaranId;

    public Pembayaran $pembayaran;

    public ?string $buktiBayarUrl = null;

    public bool $confirmingDelete = false;

    public function mount(int $id): void
    {
        $this->pembayaranId = $id;

        $this->loadPembayaran();
        $this->resolveBackUrl();
    }

    private function loadPembayaran(): void
    {
        $this->pembayaran = Pembayaran::with([
            'tagihan.mahasiswa.prodi',
            'tagihan.semester',
            'tagihan.tagihanRinci.komponenBiaya',
        ])->findOrFail($this->pembayaranId);

        $base = rtrim((string) config('app.url'), '/');
        $this->buktiBayarUrl = $this->pembayaran->bukti_bayar
            ? $base.'/storage/'.ltrim($this->pembayaran->bukti_bayar, '/')
            : null;
    }

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * Sama persis dengan PembayaranController::destroy.
     */
    public function delete()
    {
        $tagihan = $this->pembayaran->tagihan;

        DB::transaction(function () use ($tagihan) {
            $this->pembayaran->delete();

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update(['status' => 'paid']);
            } else {
                $tagihan->update(['status' => 'unpaid', 'tanggal_pembayaran' => null]);
            }
        });

        session()->flash('status', 'Pembayaran dihapus.');

        return redirect($this->backUrl);
    }

    /**
     * Sama persis dengan PembayaranController::approve.
     */
    public function approve(): void
    {
        if ($this->pembayaran->approved_at !== null) {
            $this->addError('approve', 'Pembayaran sudah disetujui sebelumnya.');

            return;
        }

        $tagihan = $this->pembayaran->tagihan;
        $user = Auth::user();
        $approver = $user?->name ?? $user?->email ?? 'admin';

        DB::transaction(function () use ($tagihan, $approver) {
            $this->pembayaran->update([
                'approved_at' => now(),
                'approved_by' => $approver,
            ]);

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                    'tanggal_pembayaran' => $this->pembayaran->tanggal_pembayaran ?? now(),
                ]);
            }

            $mahasiswa = $tagihan?->mahasiswa;
            if ($mahasiswa && $mahasiswa->id_user) {
                Notifikasi::kirim(
                    idUser: $mahasiswa->id_user,
                    tipe: 'pembayaran_acc',
                    judul: 'Pembayaran disetujui',
                    pesan: "Pembayaran {$this->pembayaran->no_pembayaran} untuk tagihan {$tagihan->no_tagihan} sudah disetujui.",
                    url: '/mahasiswa/tagihan',
                );
            }
        });

        $this->loadPembayaran();
        session()->flash('status', 'Pembayaran disetujui.');
    }

    public function render()
    {
        return view('livewire.admin.pembayaran.show')->extends('layouts.web');
    }
}
