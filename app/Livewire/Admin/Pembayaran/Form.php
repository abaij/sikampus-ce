<?php

namespace App\Livewire\Admin\Pembayaran;

use App\Livewire\Admin\Pembayaran\Concerns\ForwardsIndexState;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $pembayaranId = null;

    // Mode tambah: cari mahasiswa lewat NIM persis, mirip
    // PembayaranController::getUnpaidTagihanByNim.
    public string $nim = '';

    public ?int $id_tagihan = null;

    // Terikat <input type="number">/<input type="date">, bukan <select> — tetap string (bukan
    // int/?date) karena input kosong mengirim "". Lihat SKILL.md.
    public string $nominal = '';

    public string $tanggal_pembayaran = '';

    public string $metode_pembayaran = '';

    public string $keterangan = '';

    // Info tampilan saja (mode edit): tagihan tidak bisa diganti setelah pembayaran dibuat,
    // sama seperti PembayaranController::update yang tidak menerima id_tagihan.
    public ?Tagihan $lockedTagihan = null;

    public float $sisaTagihanSaatIni = 0;

    public function mount(?int $id = null): void
    {
        $this->pembayaranId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            $this->tanggal_pembayaran = now()->format('Y-m-d');

            return;
        }

        $pembayaran = Pembayaran::with(['tagihan.mahasiswa.prodi', 'tagihan.semester'])->findOrFail($id);

        $this->id_tagihan = $pembayaran->id_tagihan;
        $this->lockedTagihan = $pembayaran->tagihan;
        $this->nominal = (string) $pembayaran->nominal;
        $this->tanggal_pembayaran = $pembayaran->tanggal_pembayaran?->format('Y-m-d') ?? '';
        $this->metode_pembayaran = (string) $pembayaran->metode_pembayaran;
        $this->keterangan = (string) $pembayaran->keterangan;

        $totalApprovedLain = Pembayaran::approvedQueryForTagihan($this->lockedTagihan->id)
            ->where('id', '!=', $id)
            ->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($this->lockedTagihan);
        $this->sisaTagihanSaatIni = max(0.0, (float) $this->lockedTagihan->total - (float) $totalApprovedLain - $kredit);
    }

    /**
     * Sama persis dengan PembayaranController::getUnpaidTagihanByNim.
     *
     * @return array{mahasiswa: ?Mahasiswa, tagihan: Collection}
     */
    #[Computed]
    public function mahasiswaTagihan(): array
    {
        if (trim($this->nim) === '') {
            return ['mahasiswa' => null, 'tagihan' => collect()];
        }

        $mahasiswa = Mahasiswa::where('nim', $this->nim)->first();
        if (! $mahasiswa) {
            return ['mahasiswa' => null, 'tagihan' => collect()];
        }

        $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)
            ->whereIn('status', ['unpaid', 'expired'])
            ->with(['semester'])
            ->orderByDesc('tanggal_tagihan')
            ->get();

        $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds($tagihan->pluck('id')->all());

        $tagihan = $tagihan
            ->map(function (Tagihan $t) use ($kredit) {
                $totalPembayaran = (float) Pembayaran::approvedQueryForTagihan($t->id)->sum('nominal');
                $kreditBaris = (float) ($kredit[$t->id] ?? 0);
                $t->setAttribute('kredit_keringanan', $kreditBaris);
                $t->setAttribute('sisa_tagihan', (float) $t->total - $totalPembayaran - $kreditBaris);

                return $t;
            })
            ->filter(fn (Tagihan $t) => $t->sisa_tagihan > 0)
            ->values();

        return ['mahasiswa' => $mahasiswa, 'tagihan' => $tagihan];
    }

    public function updatingNim(): void
    {
        $this->id_tagihan = null;
        $this->nominal = '';
    }

    public function selectTagihan(int $id): void
    {
        $this->id_tagihan = $id;

        $tagihan = $this->mahasiswaTagihan['tagihan']->firstWhere('id', $id);
        if ($tagihan) {
            $this->nominal = (string) $tagihan->sisa_tagihan;
        }
    }

    protected function rules(): array
    {
        if ($this->pembayaranId) {
            return [
                'nominal' => ['required', 'numeric', 'min:0'],
                'tanggal_pembayaran' => ['nullable', 'date'],
                'metode_pembayaran' => ['nullable', 'string', 'max:50'],
                'keterangan' => ['nullable', 'string'],
            ];
        }

        return [
            'id_tagihan' => ['required', 'integer', 'exists:tagihan,id'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal_pembayaran' => ['nullable', 'date'],
            'metode_pembayaran' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'id_tagihan.required' => 'Tagihan harus dipilih.',
            'id_tagihan.exists' => 'Tagihan tidak valid.',
            'nominal.required' => 'Nominal harus diisi.',
        ];
    }

    /**
     * Sama persis dengan PembayaranController::store/update.
     */
    public function save()
    {
        $validated = $this->validate();

        if ($this->pembayaranId) {
            return $this->saveUpdate($validated);
        }

        return $this->saveCreate($validated);
    }

    private function saveCreate(array $validated)
    {
        $tagihan = Tagihan::findOrFail($validated['id_tagihan']);

        $totalPembayaran = (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);
        $sisaTagihan = (float) $tagihan->total - $totalPembayaran - $kredit;

        if ($sisaTagihan <= 0) {
            $this->addError('id_tagihan', $kredit > 0
                ? 'Tagihan ini sudah tertutup oleh pembayaran dan keringanan biaya.'
                : 'Tagihan ini sudah lunas.');

            return;
        }

        if ((float) $validated['nominal'] > $sisaTagihan) {
            $this->addError('nominal', 'Nominal pembayaran tidak boleh melebihi sisa tagihan yang belum dibayar.');

            return;
        }

        $user = Auth::user();
        $userName = $user?->name ?? 'admin';

        DB::transaction(function () use ($validated, $tagihan, $userName) {
            $pembayaran = Pembayaran::create([
                'id_tagihan' => $validated['id_tagihan'],
                'no_pembayaran' => $this->generateNoPembayaran(),
                'nominal' => $validated['nominal'],
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?: now(),
                'metode_pembayaran' => $validated['metode_pembayaran'] ?: null,
                'keterangan' => $validated['keterangan'] ?: null,
                'approved_at' => now(),
                'approved_by' => $userName,
                'created_by' => $userName,
            ]);

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                    'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?: now(),
                ]);
            }

            $this->pembayaranId = $pembayaran->id;
        });

        session()->flash('status', 'Pembayaran berhasil disimpan.');

        return redirect($this->backUrl);
    }

    private function saveUpdate(array $validated)
    {
        $pembayaran = Pembayaran::findOrFail($this->pembayaranId);
        $tagihan = $pembayaran->tagihan;

        $totalApprovedLain = Pembayaran::approvedQueryForTagihan($tagihan->id)
            ->where('id', '!=', $pembayaran->id)
            ->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);

        if ((float) $tagihan->total - $kredit < (float) $validated['nominal'] + (float) $totalApprovedLain) {
            $this->addError('nominal', 'Nominal pembayaran tidak boleh melebihi total tagihan.');

            return;
        }

        DB::transaction(function () use ($validated, $pembayaran, $tagihan) {
            $pembayaran->update([
                'nominal' => $validated['nominal'],
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?: null,
                'metode_pembayaran' => $validated['metode_pembayaran'] ?: null,
                'keterangan' => $validated['keterangan'] ?: null,
            ]);

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                    'tanggal_pembayaran' => $pembayaran->tanggal_pembayaran,
                ]);
            } else {
                $tagihan->update([
                    'status' => 'unpaid',
                    'tanggal_pembayaran' => null,
                ]);
            }
        });

        session()->flash('status', 'Pembayaran berhasil disimpan.');

        return redirect($this->backUrl);
    }

    /**
     * Sama persis dengan PembayaranController::generateNoPembayaran.
     */
    private function generateNoPembayaran(): string
    {
        $date = date('Ymd');
        $prefix = "PAY-{$date}-";

        $lastPembayaran = Pembayaran::where('no_pembayaran', 'like', "{$prefix}%")
            ->orderBy('no_pembayaran', 'desc')
            ->first();

        $newNumber = $lastPembayaran ? ((int) substr($lastPembayaran->no_pembayaran, -4)) + 1 : 1;

        return $prefix.str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.admin.pembayaran.form')->extends('layouts.web');
    }
}
