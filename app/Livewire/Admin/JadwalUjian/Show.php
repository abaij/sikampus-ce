<?php

namespace App\Livewire\Admin\JadwalUjian;

use App\Livewire\Admin\JadwalUjian\Concerns\ForwardsIndexState;
use App\Models\AturanAksesKeuangan;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Tagihan;
use App\Models\Ujian;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $ujianId;

    public bool $confirmingDelete = false;

    public function mount(int $id): void
    {
        $this->ujianId = $id;
        $this->resolveBackUrl();

        $ujian = Ujian::findOrFail($id);
        $this->ensureAccess($ujian);
    }

    /**
     * Sama persis dengan UjianController — pengecekan scope prodi lewat kelas.
     */
    private function ensureAccess(Ujian $ujian): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $kelas = $ujian->kelas ?? Kelas::withTrashed()->find($ujian->id_kelas);
                if (! $kelas || ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jadwal ujian ini.');
                }
            }
        }
    }

    /**
     * Sama persis dengan UjianController::show.
     */
    #[Computed]
    public function ujian(): Ujian
    {
        $ujian = Ujian::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi.jenjang',
            'kelas.semester',
            'kelas.kelompokKelas',
            'semester',
            'ruangan',
        ])->findOrFail($this->ujianId);

        if (! $ujian->kelas) {
            abort(404, 'Kelas untuk jadwal ujian ini tidak ditemukan.');
        }

        return $ujian;
    }

    /**
     * Sama persis dengan UjianController::buildPesertaRowsForKelas.
     */
    #[Computed]
    public function peserta()
    {
        $krsRows = Krs::query()
            ->where('krs.id_kelas', (int) $this->ujian->id_kelas)
            ->where('krs.approved_at', '!=', null)
            ->whereNull('krs.deleted_at')
            ->whereHas('mahasiswa', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->with([
                'mahasiswa' => static function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi')
                        ->with(['prodi:id,nama,kode']);
                },
            ])
            ->orderBy('id')
            ->get();

        $idMahasiswaPeserta = $krsRows
            ->pluck('id_mahasiswa')
            ->filter()
            ->unique()
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        $persentaseMap = $this->persentasePembayaranAkademikUntukMahasiswa($idMahasiswaPeserta);

        return $krsRows->map(function (Krs $k) use ($persentaseMap) {
            $m = $k->mahasiswa;
            $mid = $m ? (int) $m->id : null;
            $pct = $mid !== null ? ($persentaseMap[$mid] ?? null) : null;

            return (object) [
                'id_krs' => $k->id,
                'mahasiswa' => $m,
                'persentase_pembayaran_akademik' => $pct,
                'tidak_memenuhi_syarat' => $this->pesertaTidakMemenuhiSyaratKeuangan($pct),
            ];
        })->values();
    }

    /**
     * Sama persis dengan UjianController — lookup AturanAksesKeuangan berdasar jenis ujian.
     */
    #[Computed]
    public function persentaseMinimumSyarat(): ?float
    {
        $kodeAkses = Str::lower((string) $this->ujian->jenis_ujian);
        $aturan = AturanAksesKeuangan::query()
            ->where('kode_akses', $kodeAkses)
            ->where('status', 'active')
            ->first();

        return $aturan && $aturan->persentase_minimum !== null ? (float) $aturan->persentase_minimum : null;
    }

    private function pesertaTidakMemenuhiSyaratKeuangan(?float $pct): bool
    {
        $min = $this->persentaseMinimumSyarat;
        if ($min === null) {
            return false;
        }
        if ($pct === null) {
            return true;
        }

        return $pct < $min;
    }

    /**
     * Persentase pelunasan bagian tagihan yang komponennya akademik — sama persis dengan
     * UjianController::persentasePembayaranAkademikUntukMahasiswa.
     *
     * @param  list<int>  $idMahasiswa
     * @return array<int, float|null>
     */
    private function persentasePembayaranAkademikUntukMahasiswa(array $idMahasiswa): array
    {
        $idMahasiswa = array_values(array_unique(array_filter(
            array_map('intval', $idMahasiswa),
            static fn (int $i) => $i > 0
        )));
        if ($idMahasiswa === []) {
            return [];
        }

        $totalAkademik = array_fill_keys($idMahasiswa, 0.0);
        $terbayarAkademik = array_fill_keys($idMahasiswa, 0.0);

        $tagihans = Tagihan::query()
            ->whereIn('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->with([
                'tagihanRinci' => static function ($q) {
                    $q->whereNull('deleted_at')->with('komponenBiaya');
                },
                'pembayaran' => static function ($q) {
                    $q->whereNull('deleted_at')->whereNotNull('approved_at');
                },
            ])
            ->get();

        foreach ($tagihans as $t) {
            $mid = (int) $t->id_mahasiswa;
            if (! array_key_exists($mid, $terbayarAkademik)) {
                continue;
            }

            $totalTagihan = 0.0;
            $totalAkademikTagihan = 0.0;
            foreach ($t->tagihanRinci as $r) {
                $n = (float) $r->nominal;
                $totalTagihan += $n;
                $kb = $r->komponenBiaya;
                if ($kb && (bool) $kb->is_akademik) {
                    $totalAkademikTagihan += $n;
                }
            }

            if ($totalAkademikTagihan > 0) {
                $totalAkademik[$mid] += $totalAkademikTagihan;
            }
            if ($totalTagihan > 0 && $totalAkademikTagihan > 0) {
                $dibayar = (float) $t->pembayaran->sum(static fn ($p) => (float) $p->nominal);
                $terbayarAkademik[$mid] += $dibayar * ($totalAkademikTagihan / $totalTagihan);
            }
        }

        $out = [];
        foreach ($idMahasiswa as $mid) {
            $tot = (float) ($totalAkademik[$mid] ?? 0);
            if ($tot <= 0) {
                $out[$mid] = null;

                continue;
            }
            $paid = (float) ($terbayarAkademik[$mid] ?? 0);
            $pct = ($paid / $tot) * 100;
            $out[$mid] = round(min(100, max(0, $pct)), 2);
        }

        return $out;
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
     * Sama persis dengan UjianController::destroy.
     */
    public function delete()
    {
        $ujian = Ujian::findOrFail($this->ujianId);
        $this->ensureAccess($ujian);

        $user = Auth::user();
        $actor = $user ? ((string) ($user->name ?? $user->id)) : 'system';
        $ujian->update(['deleted_by' => $actor]);
        $ujian->delete();

        session()->flash('status', 'Jadwal ujian dihapus.');

        return redirect()->route('admin.akademik.jadwal-ujian');
    }

    public function render()
    {
        return view('livewire.admin.jadwal-ujian.show')->extends('layouts.web');
    }
}
