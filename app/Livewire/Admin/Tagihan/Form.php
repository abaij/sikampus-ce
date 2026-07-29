<?php

namespace App\Livewire\Admin\Tagihan;

use App\Livewire\Admin\Tagihan\Concerns\ForwardsIndexState;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Models\TagihanRinci;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $tagihanId = null;

    public ?int $id_mahasiswa = null;

    public string $mahasiswaLabel = '';

    public string $mahasiswaSearch = '';

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    public ?int $id_semester = null;

    public string $status = 'unpaid';

    /** Kosong = tagihan tanpa tahap. Diikat ke <input type="number">, jadi string. */
    public string $tahap = '';

    public string $tanggal_tagihan = '';

    public string $tanggal_jatuh_tempo = '';

    public string $tanggal_pembayaran = '';

    public string $keterangan = '';

    /** @var array<int, array{id_komponen_biaya: string, nominal: string}> */
    public array $rincian = [];

    public function mount(?int $id = null): void
    {
        $this->tagihanId = $id;
        $this->tanggal_tagihan = now()->format('Y-m-d');
        $this->rincian = [['id_komponen_biaya' => '', 'nominal' => '']];
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $tagihan = Tagihan::with(['mahasiswa', 'tagihanRinci'])->findOrFail($id);

        $this->ensureAccess($tagihan);

        $this->id_mahasiswa = $tagihan->id_mahasiswa;
        $this->mahasiswaLabel = trim(($tagihan->mahasiswa->nim ?? '—').' — '.($tagihan->mahasiswa->nama ?? ''));
        $this->id_semester = $tagihan->id_semester;
        $this->tahap = $tagihan->tahap !== null ? (string) $tagihan->tahap : '';
        $this->status = $tagihan->status ?? 'unpaid';
        $this->tanggal_tagihan = $tagihan->tanggal_tagihan?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->tanggal_jatuh_tempo = $tagihan->tanggal_jatuh_tempo?->format('Y-m-d') ?? '';
        $this->tanggal_pembayaran = $tagihan->tanggal_pembayaran?->format('Y-m-d') ?? '';
        $this->keterangan = (string) $tagihan->keterangan;

        if ($tagihan->tagihanRinci->isNotEmpty()) {
            $this->rincian = $tagihan->tagihanRinci
                ->map(fn (TagihanRinci $rinci) => [
                    'id_komponen_biaya' => (string) $rinci->id_komponen_biaya,
                    'nominal' => (string) $rinci->nominal,
                ])
                ->values()
                ->all();
        }
    }

    private function ensureAccess(Tagihan $tagihan): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasScopeRestriction()) {
            return;
        }

        $allowedProdiIds = $user->getAllowedProdiIds();
        if ($allowedProdiIds === null) {
            return;
        }

        $prodiId = (int) ($tagihan->mahasiswa?->id_prodi ?? 0);
        if ($allowedProdiIds === [] || ! in_array($prodiId, $allowedProdiIds, true)) {
            abort(403, 'Tagihan tidak termasuk lingkup akses Anda.');
        }
    }

    /**
     * Cari-lalu-pilih mahasiswa — mirip DosenWali\Show::mahasiswaResults. Tidak dibatasi scope
     * prodi, sama seperti TagihanController::store yang tidak melakukan pengecekan scope saat
     * membuat tagihan baru.
     */
    #[Computed]
    public function mahasiswaResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        return Mahasiswa::query()
            ->with('prodi:id,nama')
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            })
            ->orderBy('nama')
            ->limit(20)
            ->get(['id', 'nama', 'nim', 'id_prodi']);
    }

    public function selectMahasiswa(int $id, string $label): void
    {
        $this->id_mahasiswa = $id;
        $this->mahasiswaLabel = $label;
        $this->mahasiswaSearch = '';
    }

    public function clearMahasiswa(): void
    {
        $this->id_mahasiswa = null;
        $this->mahasiswaLabel = '';
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function komponenBiayaOptions(): array
    {
        return KomponenBiaya::orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($k) => [$k->id => "{$k->nama} ({$k->kode})"])
            ->all();
    }

    public function addRincian(): void
    {
        $this->rincian[] = ['id_komponen_biaya' => '', 'nominal' => ''];
    }

    public function removeRincian(int $index): void
    {
        if (count($this->rincian) <= 1) {
            return;
        }

        unset($this->rincian[$index]);
        $this->rincian = array_values($this->rincian);
    }

    public function totalRincian(): float
    {
        return collect($this->rincian)->sum(fn ($r) => (float) ($r['nominal'] ?: 0));
    }

    /**
     * Setelah memilih komponen biaya pada satu baris rincian, cari nominal dari struktur biaya
     * (kombinasi kategori biaya aktif, prodi, angkatan mahasiswa, komponen, tahap 1) dan isi
     * otomatis kalau baris itu belum ada nominalnya — meniru fetchStrukturBiaya di halaman
     * frontend tambah/ubah tagihan.
     */
    public function updated($property, $value): void
    {
        if (! preg_match('/^rincian\.(\d+)\.id_komponen_biaya$/', $property, $m)) {
            return;
        }

        $index = (int) $m[1];
        $this->autoFillNominal($index);
    }

    public function autoFillNominal(int $index): void
    {
        if (! isset($this->rincian[$index]) || $this->rincian[$index]['nominal'] !== '') {
            return;
        }

        $idKomponenBiaya = $this->rincian[$index]['id_komponen_biaya'] ?? '';
        if ($idKomponenBiaya === '' || ! $this->id_mahasiswa) {
            return;
        }

        $mahasiswa = Mahasiswa::with(['kategori_biaya_mahasiswa' => function ($q) {
            $q->where('status', 'active');
        }])->find($this->id_mahasiswa);

        if (! $mahasiswa || ! $mahasiswa->id_semester_masuk) {
            return;
        }

        $idKategoriBiaya = $mahasiswa->kategori_biaya_mahasiswa->first()->id_kategori_biaya ?? null;

        $strukturBiaya = StrukturBiaya::query()
            ->where('id_angkatan', $mahasiswa->id_semester_masuk)
            ->where('id_komponen_biaya', (int) $idKomponenBiaya)
            ->where('tahap', 1)
            ->where(function ($q) use ($idKategoriBiaya) {
                $idKategoriBiaya ? $q->where('id_kategori_biaya', $idKategoriBiaya) : $q->whereNull('id_kategori_biaya');
            })
            ->where(function ($q) use ($mahasiswa) {
                $mahasiswa->id_prodi ? $q->where('id_prodi', $mahasiswa->id_prodi) : $q->whereNull('id_prodi');
            })
            ->first();

        if ($strukturBiaya) {
            $this->rincian[$index]['nominal'] = (string) $strukturBiaya->nominal;
        }
    }

    /**
     * Sama persis dengan TagihanController::store/update — total selalu dihitung dari jumlah
     * rincian (bukan diterima dari input terpisah seperti di frontend, yang hanya menampilkan
     * hasil kalkulasi yang sama).
     */
    protected function rules(): array
    {
        return [
            'id_mahasiswa' => ['required', 'integer', 'exists:mahasiswa,id'],
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['unpaid', 'paid', 'expired'])],
            'tanggal_tagihan' => ['nullable', 'date'],
            'tanggal_jatuh_tempo' => ['nullable', 'date', 'after_or_equal:tanggal_tagihan'],
            'tanggal_pembayaran' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.id_komponen_biaya' => ['required', 'integer', 'exists:komponen_biaya,id'],
            'rincian.*.nominal' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'id_mahasiswa.required' => 'Mahasiswa harus dipilih.',
            'id_semester.required' => 'Semester harus dipilih.',
            'rincian.required' => 'Minimal harus ada satu rincian tagihan.',
            'rincian.min' => 'Minimal harus ada satu rincian tagihan.',
            'rincian.*.id_komponen_biaya.required' => 'Komponen biaya harus dipilih.',
            'rincian.*.nominal.required' => 'Nominal harus diisi.',
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $komponenIds = array_column($validated['rincian'], 'id_komponen_biaya');
        if (count($komponenIds) !== count(array_unique($komponenIds))) {
            $this->addError('rincian', 'Komponen biaya duplikat dalam rincian tagihan.');

            return;
        }

        // Sama persis dengan TagihanController::tagihanKembarExists — satu tagihan per
        // (mahasiswa, semester, tahap). Aturan lama yang mengabaikan tahap membuat tagihan hasil
        // generate multi-tahap tidak pernah bisa disimpan ulang dari form ini.
        $tahap = $validated['tahap'] !== '' && $validated['tahap'] !== null ? (int) $validated['tahap'] : null;

        $exists = Tagihan::where('id_mahasiswa', $validated['id_mahasiswa'])
            ->where('id_semester', $validated['id_semester'])
            ->when(
                $tahap === null,
                fn ($q) => $q->whereNull('tahap'),
                fn ($q) => $q->where('tahap', $tahap)
            )
            ->when($this->tagihanId, fn ($q) => $q->where('id', '!=', $this->tagihanId))
            ->exists();

        if ($exists) {
            $this->addError('id_semester', $tahap === null
                ? 'Tagihan tanpa tahap untuk mahasiswa dan semester ini sudah ada.'
                : "Tagihan tahap {$tahap} untuk mahasiswa dan semester ini sudah ada.");

            return;
        }

        $total = collect($validated['rincian'])->sum('nominal');

        DB::transaction(function () use ($validated, $total, $tahap) {
            if ($this->tagihanId) {
                $tagihan = Tagihan::findOrFail($this->tagihanId);
                $tagihan->update([
                    'id_mahasiswa' => $validated['id_mahasiswa'],
                    'id_semester' => $validated['id_semester'],
                    'total' => $total,
                    'tahap' => $tahap,
                    'status' => $validated['status'] ?: 'unpaid',
                    'tanggal_tagihan' => $validated['tanggal_tagihan'] ?: null,
                    'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?: null,
                    'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?: null,
                    'keterangan' => $validated['keterangan'] ?: null,
                ]);

                // forceDelete, bukan delete — TagihanRinci pakai SoftDeletes, dan baris yang
                // di-soft-delete tetap menempati unique index (id_tagihan, id_komponen_biaya).
                // Rincian di sini selalu diganti sepenuhnya sebagai satu set, jadi tidak ada
                // gunanya dipertahankan sebagai riwayat soft-delete (lihat pola yang sama di
                // KurikulumMatkul::bobotPenilaian()->forceDelete() saat mengganti relasi anak).
                TagihanRinci::where('id_tagihan', $tagihan->id)->forceDelete();
            } else {
                $tagihan = Tagihan::create([
                    'id_mahasiswa' => $validated['id_mahasiswa'],
                    'id_semester' => $validated['id_semester'],
                    'no_tagihan' => $this->generateNoTagihan(),
                    'total' => $total,
                    'tahap' => $tahap,
                    'status' => $validated['status'] ?: 'unpaid',
                    'tanggal_tagihan' => $validated['tanggal_tagihan'] ?: now(),
                    'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?: null,
                    'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?: null,
                    'keterangan' => $validated['keterangan'] ?: null,
                ]);
            }

            foreach ($validated['rincian'] as $rinci) {
                TagihanRinci::create([
                    'id_tagihan' => $tagihan->id,
                    'id_komponen_biaya' => $rinci['id_komponen_biaya'],
                    'nominal' => $rinci['nominal'],
                ]);
            }

            $this->tagihanId = $tagihan->id;
        });

        session()->flash('status', 'Tagihan berhasil disimpan.');

        return redirect($this->backUrl);
    }

    /**
     * Sama persis dengan TagihanController::generateNoTagihan.
     */
    private function generateNoTagihan(): string
    {
        $date = date('Ymd');
        $prefix = "INV-{$date}-";

        $lastTagihan = Tagihan::where('no_tagihan', 'like', "{$prefix}%")
            ->orderBy('no_tagihan', 'desc')
            ->first();

        $newNumber = $lastTagihan ? ((int) substr($lastTagihan->no_tagihan, -4)) + 1 : 1;

        return $prefix.str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.admin.tagihan.form')->extends('layouts.web');
    }
}
