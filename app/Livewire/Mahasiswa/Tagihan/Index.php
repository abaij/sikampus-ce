<?php

namespace App\Livewire\Mahasiswa\Tagihan;

use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use App\Services\PenomoranDokumen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public ?int $payModalTagihanId = null;

    public string $tipeBayar = 'penuh';

    public string $nominalPartial = '';

    /** @var TemporaryUploadedFile|null */
    public $buktiFile = null;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan TagihanController::getTagihanMahasiswa.
     */
    #[Computed]
    public function tagihanList()
    {
        $tagihanList = Tagihan::with(['semester', 'tagihanRinci.komponenBiaya'])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->whereNotNull('tanggal_tagihan')
            ->whereDate('tanggal_tagihan', '<=', Carbon::today())
            ->orderByDesc('tanggal_tagihan')
            ->get();

        $kreditKeringanan = KeringananBiayaKreditService::kreditUntukTagihanIds($tagihanList->pluck('id')->all());

        return $tagihanList->map(function (Tagihan $tagihan) use ($kreditKeringanan) {
            $totalPembayaranDisetujui = (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
            $totalSemuaPembayaran = (float) Pembayaran::where('id_tagihan', $tagihan->id)->whereNull('deleted_at')->sum('nominal');
            $kredit = (float) ($kreditKeringanan[$tagihan->id] ?? 0);
            $sisaTagihan = (float) $tagihan->total - $totalPembayaranDisetujui - $kredit;
            $sisaDapatDibayar = max(0.0, (float) $tagihan->total - $totalSemuaPembayaran - $kredit);

            $tagihan->total_pembayaran = $totalPembayaranDisetujui;
            $tagihan->kredit_keringanan = $kredit;
            $tagihan->sisa_tagihan = $sisaTagihan;
            $tagihan->sisa_dapat_dibayar = $sisaDapatDibayar;

            return $tagihan;
        });
    }

    #[Computed]
    public function payModalTagihan(): ?Tagihan
    {
        if ($this->payModalTagihanId === null) {
            return null;
        }

        return $this->tagihanList->firstWhere('id', $this->payModalTagihanId);
    }

    public function openBayarModal(int $id): void
    {
        $this->payModalTagihanId = $id;
        $this->tipeBayar = 'penuh';
        $this->nominalPartial = '';
        $this->buktiFile = null;
        $this->resetValidation();
    }

    public function closeBayarModal(): void
    {
        $this->payModalTagihanId = null;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan PembayaranController::storeByMahasiswa.
     */
    public function submitBayar(): void
    {
        $tagihan = $this->payModalTagihan;
        abort_if($tagihan === null, 404, 'Tagihan tidak ditemukan.');

        $this->validate([
            'tipeBayar' => ['required', 'in:penuh,sebagian'],
            'buktiFile' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [], ['buktiFile' => 'bukti pembayaran']);

        $totalSemua = (float) Pembayaran::where('id_tagihan', $tagihan->id)->whereNull('deleted_at')->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);
        $sisaDapatDialokasikan = (float) $tagihan->total - $totalSemua - $kredit;

        if ($sisaDapatDialokasikan <= 0) {
            $this->addError('buktiFile', $kredit > 0
                ? 'Tagihan ini sudah tidak memiliki sisa yang dapat dibayarkan setelah keringanan biaya (termasuk pembayaran yang menunggu verifikasi).'
                : 'Tagihan ini sudah tidak memiliki sisa yang dapat dibayarkan (termasuk pembayaran yang menunggu verifikasi).');

            return;
        }

        if ($this->tipeBayar === 'penuh') {
            $nominal = $sisaDapatDialokasikan;
        } else {
            $nominal = (float) str_replace(',', '.', str_replace('.', '', $this->nominalPartial));
            if ($nominal <= 0) {
                $this->addError('nominalPartial', 'Isi nominal pembayaran yang valid.');

                return;
            }
            if ($nominal > $sisaDapatDialokasikan) {
                $this->addError('nominalPartial', 'Nominal melebihi sisa yang dapat dibayarkan.');

                return;
            }
        }

        $file = $this->buktiFile;
        $filename = 'bukti_tagihan_'.$tagihan->id.'_'.time().'_'.uniqid('', true).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('pembayaran/bukti', $filename, 'public');

        // Dibungkus transaksi supaya penguncian nomor di PenomoranDokumen benar-benar berlaku:
        // ini jalur paling ramai (banyak mahasiswa mengunggah bukti bersamaan saat masa
        // pembayaran), dan tanpa transaksi lockForUpdate langsung dilepas begitu query selesai.
        // `created_by` diisi otomatis oleh trait MencatatPelaku dengan pengenal user mahasiswa.
        DB::transaction(function () use ($tagihan, $nominal, $path): void {
            Pembayaran::create([
                'id_tagihan' => $tagihan->id,
                'no_pembayaran' => PenomoranDokumen::pembayaran(),
                'nominal' => $nominal,
                'tanggal_pembayaran' => now(),
                'metode_pembayaran' => 'upload_mahasiswa',
                'bukti_bayar' => $path,
                'keterangan' => null,
                'approved_at' => null,
                'approved_by' => null,
            ]);
        });

        $this->payModalTagihanId = null;
        $this->resetValidation();
        unset($this->tagihanList);
        session()->flash('status', 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi.');
    }

    public function render()
    {
        return view('livewire.mahasiswa.tagihan.index')->extends('layouts.mahasiswa');
    }
}
