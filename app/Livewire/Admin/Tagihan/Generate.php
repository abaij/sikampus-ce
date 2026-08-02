<?php

namespace App\Livewire\Admin\Tagihan;

use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Services\JadwalTagihanTahap;
use App\Services\PenerapanTagihanTahap;
use App\Services\SeriNomorDokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Generate extends Component
{
    public string $search = '';

    public ?string $confirmingGroupKey = null;

    /** @var array<int, int> */
    public array $confirmingGroupAvailableTahap = [];

    public string $opsiTahap = 'all';

    public string $selectedTahap = '';

    // Terikat <input type="date">, jadi string. Kosong = pakai tanggal mulai periode.
    public string $tanggalTagihan = '';

    public string $tanggalJatuhTempo = '';

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

    /** Sama seperti filter di StrukturBiayaController::index. */
    private function applyStrukturBiayaProdiScope(Builder $query): void
    {
        $allowed = $this->allowedProdiIds();
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('id_prodi', $allowed);
    }

    /**
     * Sama persis dengan TagihanController::generatePreview — pengelompokan grup struktur biaya
     * berdasarkan id_periode, id_angkatan, id_prodi, id_komponen_biaya (tanpa tahap).
     */
    #[Computed]
    public function groupedStrukturBiaya()
    {
        $query = StrukturBiaya::with(['periode', 'angkatan', 'prodi', 'komponenBiaya']);
        $this->applyStrukturBiayaProdiScope($query);
        $rows = $query->get();

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $rows = $rows->filter(function (StrukturBiaya $row) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row->periode->nama ?? ''),
                    (string) ($row->periode->kode ?? ''),
                    (string) ($row->angkatan->nama ?? ''),
                    (string) ($row->angkatan->kode ?? ''),
                    (string) ($row->prodi->nama ?? ''),
                    (string) ($row->prodi->kode ?? ''),
                    (string) ($row->komponenBiaya->nama ?? ''),
                    (string) ($row->komponenBiaya->kode ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        return $rows
            ->groupBy(function (StrukturBiaya $row) {
                return implode('|', [
                    (string) $row->id_periode,
                    (string) $row->id_angkatan,
                    (string) ($row->id_prodi ?? 'null'),
                    (string) ($row->id_komponen_biaya ?? 'null'),
                ]);
            })
            ->map(function ($items, $key) {
                /** @var StrukturBiaya $first */
                $first = $items->first();
                $availableTahap = $items->pluck('tahap')->filter()->unique()->sort()->values();

                return [
                    'key' => $key,
                    'id_periode' => $first->id_periode,
                    'id_angkatan' => $first->id_angkatan,
                    'id_prodi' => $first->id_prodi,
                    'id_komponen_biaya' => $first->id_komponen_biaya,
                    'periode' => $first->periode,
                    'angkatan' => $first->angkatan,
                    'prodi' => $first->prodi,
                    'komponen_biaya' => $first->komponenBiaya,
                    'total_baris_struktur' => $items->count(),
                    'available_tahap' => $availableTahap->all(),
                ];
            })
            ->values()
            ->sortBy([
                ['id_periode', 'desc'],
                ['id_angkatan', 'desc'],
                ['id_prodi', 'asc'],
                ['id_komponen_biaya', 'asc'],
            ])
            ->values();
    }

    public function openGenerateModal(string $key): void
    {
        $group = $this->groupedStrukturBiaya->firstWhere('key', $key);
        if (! $group) {
            return;
        }

        $this->resetErrorBag();
        $this->confirmingGroupKey = $key;
        $this->confirmingGroupAvailableTahap = $group['available_tahap'];
        $this->opsiTahap = 'all';
        $this->selectedTahap = '';
    }

    public function closeGenerateModal(): void
    {
        $this->confirmingGroupKey = null;
        $this->confirmingGroupAvailableTahap = [];
        $this->opsiTahap = 'all';
        $this->selectedTahap = '';
        $this->tanggalTagihan = '';
        $this->tanggalJatuhTempo = '';
        $this->resetErrorBag();
    }

    /**
     * Jadwal untuk grup yang sedang dikonfirmasi — null kalau basis tanggalnya belum bisa
     * ditentukan (periode tanpa tanggal mulai dan operator belum mengisi tanggal).
     */
    private function jadwal(): ?JadwalTagihanTahap
    {
        $group = $this->confirmingGroupKey
            ? $this->groupedStrukturBiaya->firstWhere('key', $this->confirmingGroupKey)
            : null;

        return JadwalTagihanTahap::resolve(
            $group ? Semester::find($group['id_periode']) : null,
            $this->tanggalTagihan,
            $this->tanggalJatuhTempo,
        );
    }

    /**
     * Pratinjau jadwal di modal. Tanggalnya berasal dari periode yang ditagih atau dari isian
     * operator — dulu selalu dihitung dari bulan berjalan, sehingga menyesatkan untuk periode lama.
     */
    public function jadwalPreview(): string
    {
        $jadwal = $this->jadwal();

        if (! $jadwal) {
            return JadwalTagihanTahap::pesanTanggalTidakDiketahui();
        }

        if ($this->opsiTahap !== 'specific' || $this->selectedTahap === '') {
            return 'Semua tahapan diproses; tahap ke-n digeser n-1 bulan. '
                .$jadwal->ringkasanTahap(1).' '.$jadwal->keteranganSumber();
        }

        return $jadwal->ringkasanTahap((int) $this->selectedTahap).' '.$jadwal->keteranganSumber();
    }

    /**
     * Sama persis dengan TagihanController::generateFromStrukturBiaya, dijalankan untuk satu
     * grup yang sudah dipilih lewat modal (bukan dari request tervalidasi terpisah).
     */
    public function generate(): void
    {
        if (! $this->confirmingGroupKey) {
            return;
        }

        $group = $this->groupedStrukturBiaya->firstWhere('key', $this->confirmingGroupKey);
        if (! $group) {
            $this->closeGenerateModal();

            return;
        }

        if ($this->opsiTahap === 'specific' && $this->selectedTahap === '') {
            $this->addError('selectedTahap', 'Tahap wajib dipilih jika opsi tahap tertentu dipakai.');

            return;
        }

        if ($this->tanggalJatuhTempo !== '' && $this->tanggalTagihan !== ''
            && $this->tanggalJatuhTempo < $this->tanggalTagihan) {
            $this->addError('tanggalJatuhTempo', 'Jatuh tempo tidak boleh mendahului tanggal tagihan.');

            return;
        }

        $jadwal = $this->jadwal();
        if (! $jadwal) {
            $this->addError('tanggalTagihan', JadwalTagihanTahap::pesanTanggalTidakDiketahui());

            return;
        }

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowed = $user->getAllowedProdiIds();
            if ($allowed !== null) {
                if ($group['id_prodi'] === null) {
                    $this->addError('confirmingGroupKey', 'Akun terbatas scope hanya dapat generate tagihan per program studi dalam lingkup akses (bukan grup semua prodi).');

                    return;
                }

                if (! in_array((int) $group['id_prodi'], $allowed, true)) {
                    abort(403, 'Program studi tidak termasuk lingkup akses Anda.');
                }
            }
        }

        $specificTahap = $this->opsiTahap === 'specific' ? (int) $this->selectedTahap : null;

        $strukturQuery = StrukturBiaya::query()
            ->where('id_periode', $group['id_periode'])
            ->where('id_angkatan', $group['id_angkatan']);
        $this->applyStrukturBiayaProdiScope($strukturQuery);

        $group['id_prodi'] === null
            ? $strukturQuery->whereNull('id_prodi')
            : $strukturQuery->where('id_prodi', $group['id_prodi']);
        $group['id_komponen_biaya'] === null
            ? $strukturQuery->whereNull('id_komponen_biaya')
            : $strukturQuery->where('id_komponen_biaya', $group['id_komponen_biaya']);

        if ($specificTahap !== null) {
            $strukturQuery->where('tahap', $specificTahap);
        }

        $strukturRows = $strukturQuery->get();
        if ($strukturRows->isEmpty()) {
            $this->addError('confirmingGroupKey', 'Tidak ada struktur biaya untuk kombinasi filter ini.');

            return;
        }

        $mahasiswaQuery = Mahasiswa::query()
            ->with(['kategori_biaya_mahasiswa' => function ($q) {
                $q->where('status', 'active');
            }])
            ->where('id_semester_masuk', $group['id_angkatan']);

        if ($user && $user->hasScopeRestriction()) {
            $allowedMhs = $user->getAllowedProdiIds();
            if ($allowedMhs !== null && $allowedMhs !== []) {
                $mahasiswaQuery->whereIn('id_prodi', $allowedMhs);
            }
        }

        if ($group['id_prodi'] !== null) {
            $mahasiswaQuery->where('id_prodi', $group['id_prodi']);
        }

        $mahasiswaList = $mahasiswaQuery->get();
        if ($mahasiswaList->isEmpty()) {
            $this->closeGenerateModal();
            session()->flash('status', 'Tidak ada mahasiswa yang cocok untuk kombinasi filter ini.');

            return;
        }

        $keteranganBase = 'Generate otomatis dari struktur biaya';
        // Sama seperti di controller: nomor dokumen dikunci sekali lalu dilanjutkan di memori,
        // dan tagihan yang sudah ada dipramuat dalam satu query.
        $seriNomor = SeriNomorDokumen::tagihan();
        $tagihanTerpasang = PenerapanTagihanTahap::pramuat(
            (int) $group['id_periode'],
            $mahasiswaList->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($mahasiswaList, $strukturRows, $group, $specificTahap, $keteranganBase, $jadwal, $seriNomor, $tagihanTerpasang, &$createdCount, &$updatedCount, &$skippedCount) {
            foreach ($mahasiswaList as $mahasiswa) {
                $activeKategoriId = optional($mahasiswa->kategori_biaya_mahasiswa->first())->id_kategori_biaya;

                $applicable = $strukturRows->filter(function (StrukturBiaya $row) use ($activeKategoriId) {
                    if ($row->id_kategori_biaya === null) {
                        return true;
                    }

                    return (int) $row->id_kategori_biaya === (int) $activeKategoriId;
                });

                if ($applicable->isEmpty()) {
                    $skippedCount++;

                    continue;
                }

                $allTahap = $applicable->pluck('tahap')->filter()->map(fn ($t) => (int) $t)->unique()->sort()->values();
                if ($allTahap->isEmpty()) {
                    $skippedCount++;

                    continue;
                }

                $targetTahapList = $specificTahap !== null
                    ? collect([$specificTahap])->filter(fn ($t) => $allTahap->contains($t))->values()
                    : $allTahap;

                if ($targetTahapList->isEmpty()) {
                    $skippedCount++;

                    continue;
                }

                foreach ($targetTahapList as $tahap) {
                    $rincian = $applicable
                        ->where('tahap', (int) $tahap)
                        ->groupBy('id_komponen_biaya')
                        ->map(fn ($rows, $komponenId) => [
                            'id_komponen_biaya' => $komponenId,
                            'nominal' => (float) $rows->sum('nominal'),
                        ])
                        ->values()
                        ->filter(fn ($row) => ! empty($row['id_komponen_biaya']) && $row['nominal'] > 0)
                        ->values();

                    if ($rincian->isEmpty()) {
                        $skippedCount++;

                        continue;
                    }

                    // Sama persis dengan TagihanController::generateFromStrukturBiaya: tahap
                    // disimpan di kolomnya, dan komponen yang belum tercatat digabungkan ke
                    // tagihan tahap ini alih-alih membuat seluruh generate dilewati.
                    $penerapan = PenerapanTagihanTahap::terapkan(
                        (int) $mahasiswa->id,
                        (int) $group['id_periode'],
                        (int) $tahap,
                        $rincian,
                        $jadwal->untukTahap((int) $tahap),
                        $keteranganBase,
                        fn () => $seriNomor->berikutnya(),
                        $tagihanTerpasang->get(PenerapanTagihanTahap::kunciPramuat((int) $mahasiswa->id, (int) $tahap)),
                        sudahDipramuat: true,
                    );

                    if ($penerapan['hasil'] === PenerapanTagihanTahap::DIBUAT) {
                        $tagihanTerpasang->put(
                            PenerapanTagihanTahap::kunciPramuat((int) $mahasiswa->id, (int) $tahap),
                            $penerapan['tagihan'],
                        );
                    }

                    match ($penerapan['hasil']) {
                        PenerapanTagihanTahap::DIBUAT => $createdCount++,
                        PenerapanTagihanTahap::DITAMBAH => $updatedCount++,
                        default => $skippedCount++,
                    };
                }
            }
        });

        $this->closeGenerateModal();
        $pesan = "Generate selesai. Berhasil: {$createdCount}";
        if ($updatedCount > 0) {
            $pesan .= ", Ditambahkan ke tagihan yang sudah ada: {$updatedCount}";
        }
        $pesan .= ", Dilewati: {$skippedCount}.";

        session()->flash('status', $pesan);
    }

    public function render()
    {
        return view('livewire.admin.tagihan.generate')->extends('layouts.web');
    }
}
