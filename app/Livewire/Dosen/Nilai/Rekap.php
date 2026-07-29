<?php

namespace App\Livewire\Dosen\Nilai;

use App\Models\BobotPenilaian;
use App\Models\Dosen;
use App\Models\JadwalDosen;
use App\Models\JenisPenilaian;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\NilaiRevisi;
use App\Models\Notifikasi;
use App\Models\RentangNilai;
use App\Services\NilaiKelasDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Rekap extends Component
{
    // Locked: kalkulasiDenganRentangDefault(), finalisasi(), dan saveEditNilai() memakai kelasId
    // langsung tanpa mengecek ulang akses setiap kali — tanpa ini, kelasId bisa "disentuh" lewat
    // request Livewire yang dimanipulasi untuk mengubah nilai kelas yang tidak diampu dosen ini.
    #[Locked]
    public int $kelasId;

    #[Locked]
    public int $dosenId;

    public bool $showRentangModal = false;

    /** @var array<int, array{nilai_huruf: string, nilai_angka: float, nilai_rendah: float, nilai_tinggi: float}> */
    public array $rentangForm = [];

    public bool $showEditModal = false;

    public ?int $editKrsId = null;

    public string $editHurufMutu = '';

    public string $editAngkaMutu = '';

    public string $editKeterangan = '';

    public bool $editRevisiChecked = false;

    public function mount(int $kelasId): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $kelas = Kelas::find($kelasId);
        abort_unless($kelas, 404, 'Kelas tidak ditemukan.');
        abort_unless($this->dosenHasAccess($kelas), 403, 'Anda tidak memiliki akses ke kelas ini.');

        $this->kelasId = $kelasId;
    }

    private function dosenHasAccess(Kelas $kelas): bool
    {
        if ((int) $kelas->id_dosen_pic === $this->dosenId) {
            return true;
        }

        return JadwalDosen::whereHas('jadwal', fn ($q) => $q->where('id_kelas', $kelas->id))
            ->where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->exists();
    }

    #[Computed]
    public function kelas(): Kelas
    {
        return Kelas::with(['kurikulumMatkul.matkul', 'prodi'])->findOrFail($this->kelasId);
    }

    #[Computed]
    public function data(): array
    {
        return NilaiKelasDataService::build($this->kelas);
    }

    public function jumlahTotalNilai(Collection $nilaiKomponen): ?float
    {
        return NilaiKelasDataService::jumlahTotalNilai($nilaiKomponen, $this->data['id_jenis_penilaian_kelas'], $this->data['jenis_penilaian']);
    }

    public function openRentangModal(): void
    {
        $this->rentangForm = collect($this->data['rentang_nilai'])->map(fn (RentangNilai $r) => [
            'nilai_huruf' => $r->nilai_huruf,
            'nilai_angka' => (float) $r->nilai_angka,
            'nilai_rendah' => (float) $r->nilai_rendah,
            'nilai_tinggi' => (float) $r->nilai_tinggi,
        ])->values()->all();
        $this->showRentangModal = true;
    }

    public function closeRentangModal(): void
    {
        $this->showRentangModal = false;
    }

    /**
     * Sama persis dengan NilaiController::kalkulasiNilaiAkhir — kalkulasi dengan rentang nilai
     * default jenjang, langsung disimpan ke tabel nilai (is_final tetap null/belum final).
     */
    public function kalkulasiDenganRentangDefault(): void
    {
        $kelas = $this->kelas;
        $jenjang = $kelas->prodi?->jenjang;
        abort_if(! $jenjang, 400, 'Jenjang tidak ditemukan untuk kelas ini.');

        $rentangNilaiList = RentangNilai::where('id_jenjang', $jenjang->id)->whereNull('deleted_at')->orderByDesc('nilai_tinggi')->get();
        abort_if($rentangNilaiList->isEmpty(), 400, 'Rentang nilai tidak ditemukan untuk jenjang '.$jenjang->nama);

        $result = $this->kalkulasiDanSimpan($kelas, $rentangNilaiList->map(fn (RentangNilai $r) => [
            'nilai_huruf' => $r->nilai_huruf,
            'nilai_angka' => (float) $r->nilai_angka,
            'nilai_rendah' => (float) $r->nilai_rendah,
            'nilai_tinggi' => (float) $r->nilai_tinggi,
        ])->all());

        unset($this->data);

        $message = "Kalkulasi nilai akhir berhasil. Berhasil: {$result['success_count']} mahasiswa";
        if ($result['error_count'] > 0) {
            $message .= ", gagal: {$result['error_count']} mahasiswa";
        }
        session()->flash('status', $message);
    }

    /**
     * Sama persis dengan NilaiController::kalkulasiPreview — kalkulasi dengan rentang nilai custom
     * dari form modal, langsung disimpan ke tabel nilai.
     */
    public function terapkanRentangCustom(): void
    {
        if ($this->rentangForm === []) {
            $this->addError('rentangForm', 'Rentang nilai kosong. Isi dari rentang default terlebih dahulu.');

            return;
        }

        $kelas = $this->kelas;
        $result = $this->kalkulasiDanSimpan($kelas, $this->rentangForm);

        unset($this->data);
        $this->showRentangModal = false;

        $message = "Kalkulasi dengan rentang custom berhasil disimpan. Berhasil: {$result['success_count']} mahasiswa";
        if ($result['error_count'] > 0) {
            $message .= ", gagal: {$result['error_count']} mahasiswa";
        }
        session()->flash('status', $message);
    }

    /**
     * @param  array<int, array{nilai_huruf: string, nilai_angka: float, nilai_rendah: float, nilai_tinggi: float}>  $rentangNilaiList
     * @return array{success_count: int, error_count: int}
     */
    private function kalkulasiDanSimpan(Kelas $kelas, array $rentangNilaiList): array
    {
        $rentangSorted = collect($rentangNilaiList)->sortByDesc('nilai_tinggi')->values();

        $jenisPenilaianList = JenisPenilaian::whereNull('deleted_at')->get()->keyBy('id');

        $bobotPenilaianMap = $kelas->id_kurikulum_matkul
            ? BobotPenilaian::where('id_kurikulum_matkul', $kelas->id_kurikulum_matkul)->whereNull('deleted_at')->get()->keyBy('id_jenis_penilaian')
            : collect();

        $krsList = Krs::where('id_kelas', $kelas->id)->whereNull('deleted_at')->get();
        abort_if($krsList->isEmpty(), 400, 'Tidak ada mahasiswa yang mengambil kelas ini.');

        $sks = $kelas->kurikulumMatkul?->sksLabel() ?? 0;

        $krsIds = $krsList->pluck('id')->all();
        $nilaiKomponenList = DB::table('nilai_komponen')->whereIn('id_krs', $krsIds)->whereNull('deleted_at')->get()->groupBy('id_krs');

        $successCount = 0;
        $errorCount = 0;

        DB::transaction(function () use ($krsList, $nilaiKomponenList, $jenisPenilaianList, $bobotPenilaianMap, $rentangSorted, $sks, &$successCount, &$errorCount) {
            foreach ($krsList as $krs) {
                $nilaiKomponenKrs = $nilaiKomponenList->get($krs->id, collect());
                if ($nilaiKomponenKrs->isEmpty()) {
                    $errorCount++;

                    continue;
                }

                $totalNilai = 0.0;
                $totalBobot = 0.0;
                foreach ($nilaiKomponenKrs as $nk) {
                    $jp = $jenisPenilaianList->get($nk->id_jenis_penilaian);
                    if (! $jp) {
                        continue;
                    }
                    $bobotPenilaian = $bobotPenilaianMap->get($nk->id_jenis_penilaian);
                    $bobot = $bobotPenilaian !== null ? (float) $bobotPenilaian->bobot : (float) $jp->bobot;
                    $totalNilai += (float) $nk->nilai * $bobot;
                    $totalBobot += $bobot;
                }

                $allFilled = $jenisPenilaianList->every(fn (JenisPenilaian $jp) => $nilaiKomponenKrs->contains('id_jenis_penilaian', $jp->id));
                if (! $allFilled || $totalBobot <= 0) {
                    $errorCount++;

                    continue;
                }

                $nilaiAkhir = $totalNilai / $totalBobot;
                $rentangNilai = $rentangSorted->first(fn (array $rn) => $nilaiAkhir >= $rn['nilai_rendah'] && $nilaiAkhir <= $rn['nilai_tinggi']);

                if (! $rentangNilai) {
                    $errorCount++;

                    continue;
                }

                Nilai::updateOrCreate(
                    ['id_krs' => $krs->id],
                    ['sks' => $sks, 'angka_mutu' => $rentangNilai['nilai_angka'], 'huruf_mutu' => $rentangNilai['nilai_huruf'], 'is_final' => null]
                );

                $successCount++;
            }
        });

        return ['success_count' => $successCount, 'error_count' => $errorCount];
    }

    /**
     * Sama persis dengan NilaiController::finalizeNilai.
     */
    public function finalisasi(): void
    {
        $krsIds = Krs::where('id_kelas', $this->kelasId)->whereNull('deleted_at')->pluck('id')->all();
        abort_if($krsIds === [], 400, 'Tidak ada mahasiswa di kelas ini.');

        $updated = Nilai::whereIn('id_krs', $krsIds)->whereNull('deleted_at')->update(['is_final' => true]);

        $kelas = $this->kelas;
        $namaMatkul = $kelas->kurikulumMatkul?->namaMatkulLabel() ?? 'kelas ini';
        $idMahasiswaTerdampak = Krs::whereIn('id', $krsIds)->pluck('id_mahasiswa')->unique();
        $idUserPerMahasiswa = Mahasiswa::whereIn('id', $idMahasiswaTerdampak)->whereNotNull('id_user')->pluck('id_user');
        foreach ($idUserPerMahasiswa as $idUser) {
            Notifikasi::kirim(
                idUser: $idUser,
                tipe: 'nilai_final',
                judul: 'Nilai sudah keluar',
                pesan: "Nilai {$namaMatkul} sudah difinalisasi dan bisa dilihat.",
                url: '/mahasiswa/nilai',
            );
        }

        unset($this->data);
        session()->flash('status', "Nilai berhasil difinalisasi ({$updated} mahasiswa). Nilai akan tampil di akun mahasiswa.");
    }

    public function openEditModal(int $idKrs): void
    {
        $this->resetValidation();

        $mhs = collect($this->data['mahasiswa'])->firstWhere('id_krs', $idKrs);
        abort_unless($mhs, 404, 'Data mahasiswa tidak ditemukan.');

        $this->editKrsId = $idKrs;
        $huruf = $mhs['nilai']?->huruf_mutu ?? '';
        $this->editHurufMutu = $huruf;
        $this->editAngkaMutu = $this->angkaMutuFromHuruf($huruf) ?? ($mhs['nilai']?->angka_mutu !== null ? (string) $mhs['nilai']->angka_mutu : '');
        $this->editKeterangan = '';
        $this->editRevisiChecked = false;
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editKrsId = null;
        $this->editHurufMutu = '';
        $this->editAngkaMutu = '';
        $this->editKeterangan = '';
        $this->editRevisiChecked = false;
    }

    public function updatedEditHurufMutu(): void
    {
        $angka = $this->angkaMutuFromHuruf($this->editHurufMutu);
        if ($angka !== null) {
            $this->editAngkaMutu = $angka;
        }
    }

    private function angkaMutuFromHuruf(string $huruf): ?string
    {
        if (empty($this->data['rentang_nilai']) || trim($huruf) === '') {
            return null;
        }
        $rentang = collect($this->data['rentang_nilai'])->first(fn (RentangNilai $r) => strtoupper($r->nilai_huruf) === strtoupper(trim($huruf)));

        return $rentang ? (string) $rentang->nilai_angka : null;
    }

    /**
     * Sama persis dengan NilaiController::storeRevisiNilai / updateNilaiByKrs — dipilih via
     * $editRevisiChecked, sama seperti toggle "Revisi" di modal frontend.
     */
    public function saveEditNilai(): void
    {
        $this->validate([
            'editHurufMutu' => ['required', 'string', 'max:10'],
            'editAngkaMutu' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'editKeterangan' => ['nullable', 'string', 'max:500'],
        ]);

        // editKrsId adalah properti publik (bisa "disentuh" langsung lewat request Livewire yang
        // dimanipulasi, bukan cuma lewat openEditModal) — scope ulang ke kelas ini dan cek akses
        // dosen di sini juga, jangan andalkan validasi yang sudah lewat saat modal dibuka.
        $krs = Krs::where('id', $this->editKrsId)->where('id_kelas', $this->kelasId)->first();
        abort_unless($krs, 404, 'KRS tidak ditemukan.');

        $kelas = $this->kelas;
        abort_unless($this->dosenHasAccess($kelas), 403, 'Anda tidak memiliki akses ke kelas ini.');
        $sks = $kelas->kurikulumMatkul?->sksLabel() ?? 0;
        $angkaMutu = $this->editAngkaMutu !== '' ? (float) $this->editAngkaMutu : null;

        if ($this->editRevisiChecked) {
            DB::transaction(function () use ($krs, $sks, $angkaMutu) {
                NilaiRevisi::create([
                    'id_krs' => $krs->id,
                    'angka_mutu' => $angkaMutu,
                    'huruf_mutu' => $this->editHurufMutu,
                    'keterangan' => $this->editKeterangan !== '' ? $this->editKeterangan : null,
                    'created_by' => Auth::user()->name ?? (string) Auth::id(),
                ]);

                $revisiCount = NilaiRevisi::where('id_krs', $krs->id)->whereNull('deleted_at')->count();
                $nilai = Nilai::where('id_krs', $krs->id)->whereNull('deleted_at')->first();
                $angkaMutuFinal = $angkaMutu ?? $nilai?->angka_mutu;

                Nilai::updateOrCreate(
                    ['id_krs' => $krs->id],
                    ['sks' => $sks ?: null, 'angka_mutu' => $angkaMutuFinal, 'huruf_mutu' => $this->editHurufMutu, 'revisi' => $revisiCount]
                );
            });

            session()->flash('status', 'Revisi nilai berhasil disimpan.');
        } else {
            $nilai = Nilai::where('id_krs', $krs->id)->whereNull('deleted_at')->first();
            $angkaMutuFinal = $angkaMutu ?? $nilai?->angka_mutu;

            if ($nilai) {
                $nilai->update(['huruf_mutu' => $this->editHurufMutu, 'angka_mutu' => $angkaMutuFinal]);
            } else {
                Nilai::create([
                    'id_krs' => $krs->id,
                    'sks' => $sks ?: null,
                    'huruf_mutu' => $this->editHurufMutu,
                    'angka_mutu' => $angkaMutuFinal,
                    'is_final' => false,
                ]);
            }

            session()->flash('status', 'Nilai berhasil diperbarui.');
        }

        unset($this->data);
        $this->closeEditModal();
    }

    public function render()
    {
        return view('livewire.dosen.nilai.rekap')->extends('layouts.dosen');
    }
}
