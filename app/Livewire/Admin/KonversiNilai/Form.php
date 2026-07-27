<?php

namespace App\Livewire\Admin\KonversiNilai;

use App\Models\JenisKonversiNilai;
use App\Models\KonversiNilai;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    public string $submitError = '';

    // ---- Cari-lalu-pilih mahasiswa — pola disalin dari App\Livewire\Admin\Krs\Form, sebab
    // daftar mahasiswa bisa ribuan baris (tidak realistis preload semua lewat <x-searchable-select>) ----
    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    public ?int $kurikulumId = null;

    public ?int $idJenisKonversi = null;

    /** @var array<int, array{kode_mk_lama: string, nama_mk_lama: string, sks_lama: string, nilai_lama: string, id_kurikulum_matkul: int|null, nilai_baru: string}> */
    public array $rows = [];

    public function mount(): void
    {
        $this->rows = [$this->emptyRow()];
    }

    /**
     * @return array{kode_mk_lama: string, nama_mk_lama: string, sks_lama: string, nilai_lama: string, id_kurikulum_matkul: int|null, nilai_baru: string}
     */
    private function emptyRow(): array
    {
        return [
            'kode_mk_lama' => '',
            'nama_mk_lama' => '',
            'sks_lama' => '1',
            'nilai_lama' => '',
            'id_kurikulum_matkul' => null,
            'nilai_baru' => '',
        ];
    }

    public function addRow(): void
    {
        $this->rows[] = $this->emptyRow();
    }

    public function removeRow(int $index): void
    {
        if (count($this->rows) <= 1) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    #[Computed]
    public function mahasiswaSearchResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        $query = Mahasiswa::query()
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            });

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        return $query->orderBy('nama')->limit(20)->get(['id', 'nim', 'nama']);
    }

    #[Computed]
    public function selectedMahasiswa()
    {
        if (! $this->selectedMahasiswaId) {
            return null;
        }

        return Mahasiswa::with('prodi')->find($this->selectedMahasiswaId);
    }

    /**
     * Kurikulum dibatasi ke prodi mahasiswa yang dipilih & status aktif — sama seperti
     * /kurikulum?status=active&id_prodi=... yang dipakai halaman create di frontend.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function kurikulumOptions(): array
    {
        $prodiId = $this->selectedMahasiswa?->id_prodi;

        if (! $prodiId) {
            return [];
        }

        return Kurikulum::where('id_prodi', $prodiId)
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->mapWithKeys(fn ($k) => [$k->id => "{$k->kode} — {$k->nama}"])
            ->all();
    }

    /**
     * Sama persis dengan KonversiNilaiController::optionsJenisKonversi.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function jenisKonversiOptions(): array
    {
        return JenisKonversiNilai::query()
            ->where(function ($q) {
                $q->where('is_aktif', true)->orWhereNull('is_aktif');
            })
            ->whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->mapWithKeys(fn ($j) => [$j->id => $j->nama])
            ->all();
    }

    /**
     * Sama persis dengan KonversiNilaiController::optionsKurikulumMatkul.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function kurikulumMatkulOptions(): array
    {
        if (! $this->kurikulumId) {
            return [];
        }

        return KurikulumMatkul::where('id_kurikulum', $this->kurikulumId)
            ->whereNull('deleted_at')
            ->with('matkul')
            ->orderBy('kode_matkul')
            ->get()
            ->mapWithKeys(function (KurikulumMatkul $km) {
                $m = $km->matkul;
                $kode = $km->kode_matkul ?: ($m?->kode ?? '');
                $nama = $km->nama_matkul ?: ($m?->nama ?? '');
                $sks = (int) ($km->sks ?: ($m?->sks ?? 1));

                return [$km->id => trim("{$kode} — {$nama} ({$sks} SKS)")];
            })
            ->all();
    }

    public function selectMahasiswaOption(int $id): void
    {
        $this->selectedMahasiswaId = $id;
        $this->mahasiswaSearch = '';
        $this->kurikulumId = null;
        $this->rows = [$this->emptyRow()];
        unset($this->selectedMahasiswa, $this->kurikulumOptions, $this->kurikulumMatkulOptions);
    }

    public function clearSelectedMahasiswa(): void
    {
        $this->selectedMahasiswaId = null;
        $this->kurikulumId = null;
        $this->rows = [$this->emptyRow()];
        unset($this->selectedMahasiswa, $this->kurikulumOptions, $this->kurikulumMatkulOptions);
    }

    /**
     * Ganti kurikulum me-reset pilihan mata kuliah baru di semua baris — daftar opsinya
     * (kurikulumMatkulOptions) mengikuti kurikulum yang aktif, jadi pilihan lama bisa tidak valid.
     */
    public function updatedKurikulumId(): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['id_kurikulum_matkul'] = null;
        }

        unset($this->kurikulumMatkulOptions);
    }

    protected function rules(): array
    {
        return [
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
            'kurikulumId' => ['required', 'integer', 'exists:kurikulum,id'],
            'idJenisKonversi' => ['required', 'integer', 'exists:jenis_konversi_nilai,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.kode_mk_lama' => ['required', 'string', 'max:50'],
            'rows.*.nama_mk_lama' => ['required', 'string', 'max:255'],
            'rows.*.sks_lama' => ['required', 'integer', 'min:1', 'max:255'],
            'rows.*.nilai_lama' => ['required', 'string', 'max:5'],
            'rows.*.id_kurikulum_matkul' => ['required', 'integer', 'exists:kurikulum_matkul,id'],
            'rows.*.nilai_baru' => ['required', 'string', 'max:5'],
        ];
    }

    /**
     * Sama persis dengan KonversiNilaiController::storeBulk — satu transaksi untuk semua baris
     * (all-or-nothing), bukan simpan-sebagian seperti pola batch di modul lain, karena controller
     * aslinya membungkus seluruh loop dalam satu DB::transaction.
     */
    public function save()
    {
        $this->submitError = '';

        $validated = $this->validate();

        $user = Auth::user();

        $mahasiswa = Mahasiswa::find($validated['selectedMahasiswaId']);
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && (! $mahasiswa || ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
            }
        }

        $kurikulum = Kurikulum::find($validated['kurikulumId']);
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && (! $kurikulum || ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $createdBy = $user ? ($user->name ?? (string) $user->id) : 'system';

        try {
            $created = DB::transaction(function () use ($validated, $kurikulum, $createdBy) {
                $count = 0;
                foreach ($validated['rows'] as $row) {
                    $km = KurikulumMatkul::with('matkul')
                        ->where('id', $row['id_kurikulum_matkul'])
                        ->where('id_kurikulum', $kurikulum->id)
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $km) {
                        throw new \InvalidArgumentException('Mata kuliah kurikulum tidak valid untuk kurikulum yang dipilih.');
                    }

                    $m = $km->matkul;
                    $kodeBaru = $km->kode_matkul ?: ($m?->kode ?? '');
                    $namaBaru = $km->nama_matkul ?: ($m?->nama ?? '');
                    $sksBaru = (int) ($km->sks ?: ($m?->sks ?? 1));

                    KonversiNilai::create([
                        'id_mahasiswa' => $validated['selectedMahasiswaId'],
                        'id_kurikulum' => $validated['kurikulumId'],
                        'id_jenis_konversi' => $validated['idJenisKonversi'],
                        'kode_mk_lama' => $row['kode_mk_lama'],
                        'nama_mk_lama' => $row['nama_mk_lama'],
                        'sks_lama' => (int) $row['sks_lama'],
                        'nilai_lama' => strtoupper($row['nilai_lama']),
                        'kode_mk_baru' => $kodeBaru,
                        'nama_mk_baru' => $namaBaru,
                        'sks_baru' => $sksBaru,
                        'nilai_baru' => strtoupper($row['nilai_baru']),
                        'is_approved' => true,
                        'created_by' => $createdBy,
                    ]);
                    $count++;
                }

                return $count;
            });
        } catch (QueryException $e) {
            $this->submitError = $e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')
                ? 'Data bentrok dengan konversi yang sudah ada (kombinasi mahasiswa, kode MK lama, dan kode MK baru harus unik).'
                : 'Gagal menyimpan konversi nilai: '.$e->getMessage();

            return null;
        } catch (\InvalidArgumentException $e) {
            $this->submitError = $e->getMessage();

            return null;
        }

        session()->flash('status', "{$created} konversi nilai berhasil disimpan.");

        return redirect()->route('admin.akademik.konversi-nilai');
    }

    public function render()
    {
        return view('livewire.admin.konversi-nilai.form')->extends('layouts.web');
    }
}
