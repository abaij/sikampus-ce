<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\JenisKuliah;
use App\Models\Jadwal;
use App\Models\Ruangan;
use App\Models\JadwalDosen;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Krs;
use App\Models\MatkulPrasyarat;
use App\Models\Perkuliahan;
use App\Models\Rps;
use App\Models\RpsCpl;
use App\Models\RpsCpmk;
use App\Models\RpsPembelajaran;
use App\Models\RpsSubcpmk;
use App\Models\Semester;
use App\Models\Setting;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JadwalDosenController extends Controller
{
    private function dosenCanAccessJadwal(Dosen $dosen, Jadwal $jadwal): bool
    {
        $kelas = $jadwal->kelas;
        if (! $kelas) {
            return false;
        }
        if ((int) $kelas->id_dosen_pic === (int) $dosen->id) {
            return true;
        }
        if (KelasDosen::where('id_dosen', $dosen->id)->where('id_kelas', $kelas->id)->whereNull('deleted_at')->exists()) {
            return true;
        }

        return JadwalDosen::where('id_jadwal', $jadwal->id)
            ->where('id_dosen', $dosen->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Update field bahasan pada jadwal (dosen pengampu / tim jadwal).
     */
    public function updateBahasanJadwalAmpu(Request $request, int $jadwalId): JsonResponse
    {
        $validated = $request->validate([
            'bahasan' => ['present', 'nullable', 'string', 'max:65535'],
        ]);

        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::with('kelas')->find($jadwalId);
        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke jadwal ini'], 403);
        }

        $raw = $validated['bahasan'];
        $jadwal->bahasan = $raw !== null && trim((string) $raw) !== '' ? (string) $raw : null;
        $jadwal->save();

        return response()->json([
            'message' => 'Bahasan jadwal berhasil disimpan',
            'jadwal' => [
                'id' => $jadwal->id,
                'bahasan' => $jadwal->bahasan,
            ],
        ]);
    }

    /**
     * Opsi dropdown untuk form edit jadwal (ruangan, jenis kuliah, daftar hari).
     */
    public function getOpsiEditJadwalAmpu(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $ruangan = Ruangan::query()
            ->whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (Ruangan $r) => ['id' => $r->id, 'nama' => $r->nama])
            ->values()
            ->all();

        $jenisKuliah = JenisKuliah::query()
            ->whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (JenisKuliah $j) => ['id' => $j->id, 'nama' => $j->nama])
            ->values()
            ->all();

        return response()->json([
            'hari' => Jadwal::HARI,
            'ruangan' => $ruangan,
            'jenis_kuliah' => $jenisKuliah,
        ]);
    }

    /**
     * Perbarui hari, tanggal, ruangan, dan jenis kuliah pada slot jadwal (dosen pengampu).
     */
    public function updateJadwalAmpu(Request $request, int $jadwalId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::with(['kelas', 'ruangan', 'jenisKuliah', 'dosen.dosen'])
            ->whereNull('deleted_at')
            ->find($jadwalId);

        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke jadwal ini'], 403);
        }

        $validated = $request->validate([
            'hari' => ['nullable', 'string', Rule::in(Jadwal::HARI)],
            'tanggal' => ['nullable', 'date'],
            'id_ruangan' => ['nullable', 'integer', 'exists:ruangan,id'],
            'id_jenis_kuliah' => ['nullable', 'integer', 'exists:jenis_kuliah,id'],
        ]);

        if (array_key_exists('tanggal', $validated) && ($validated['tanggal'] === '' || $validated['tanggal'] === null)) {
            $validated['tanggal'] = null;
        }

        if (array_key_exists('hari', $validated)) {
            $hari = $validated['hari'];
            $jadwal->hari = is_string($hari) && trim($hari) !== '' ? strtolower(trim($hari)) : null;
        }

        if (array_key_exists('tanggal', $validated)) {
            $jadwal->tanggal = $validated['tanggal'];
        }

        if (array_key_exists('id_ruangan', $validated)) {
            $jadwal->id_ruangan = $validated['id_ruangan'] ?: null;
        }

        if (array_key_exists('id_jenis_kuliah', $validated)) {
            $jadwal->id_jenis_kuliah = $validated['id_jenis_kuliah'] ?: null;
        }

        $jadwal->save();
        $jadwal->load(['ruangan', 'jenisKuliah', 'dosen.dosen']);

        $slots = $this->formatJadwalSlots([$jadwal]);
        $slot = $slots[0] ?? null;

        return response()->json([
            'message' => 'Jadwal berhasil diperbarui',
            'jadwal' => $slot,
        ]);
    }

    /**
     * @param  iterable<int, Jadwal>  $jadwals
     * @return list<array<string, mixed>>
     */
    private function formatJadwalSlots(iterable $jadwals): array
    {
        $hariOrder = [
            'senin' => 1,
            'selasa' => 2,
            'rabu' => 3,
            'kamis' => 4,
            'jumat' => 5,
            'sabtu' => 6,
            'minggu' => 7,
        ];

        return collect($jadwals)
            ->sortBy(function ($j) use ($hariOrder) {
                $hariNum = $hariOrder[strtolower((string) ($j->hari ?? ''))] ?? 8;
                $jamMulai = str_replace(':', '', (string) ($j->jam_mulai ?? '00:00:00'));

                return $hariNum * 100000 + (int) $jamMulai;
            })
            ->values()
            ->map(function (Jadwal $j) {
                return [
                    'id' => $j->id,
                    'hari' => $j->hari,
                    'tanggal' => $j->tanggal ? $j->tanggal->format('Y-m-d') : null,
                    'jam_mulai' => $j->jam_mulai,
                    'jam_selesai' => $j->jam_selesai,
                    'urutan_pertemuan' => $j->urutan_pertemuan,
                    'bahasan' => $j->bahasan,
                    'ruangan' => [
                        'id' => $j->ruangan->id ?? null,
                        'nama' => $j->ruangan->nama ?? null,
                    ],
                    'jenis_kuliah' => [
                        'id' => $j->jenisKuliah->id ?? null,
                        'nama' => $j->jenisKuliah->nama ?? null,
                    ],
                    'dosen' => $j->dosen->map(function ($jd) {
                        return [
                            'id' => $jd->dosen->id ?? null,
                            'nama' => $jd->dosen->nama ?? null,
                        ];
                    })->values()->all(),
                ];
            })
            ->all();
    }

    /**
     * Cocokkan satu baris perkuliahan dengan slot jadwal (sama logika ringkas dengan halaman detail dosen).
     */
    private function findPerkuliahanForJadwalSlot(Jadwal $j, Collection $perkuliahanRows): ?Perkuliahan
    {
        $slotId = (int) $j->id;
        $candidates = $perkuliahanRows->filter(fn ($p) => (int) $p->id_jadwal === $slotId);

        $ts = static function (?Perkuliahan $p): int {
            if ($p === null || ! $p->waktu_mulai) {
                return 0;
            }

            return \Carbon\Carbon::parse($p->waktu_mulai)->getTimestamp();
        };

        // Utamakan sesi yang sedang berlangsung (sudah mulai, belum selesai)
        $ongoing = $candidates
            ->filter(function (Perkuliahan $p) {
                return $p->waktu_mulai && ! $p->waktu_selesai;
            })
            ->sortByDesc(fn (Perkuliahan $p) => $ts($p))
            ->first();

        if ($ongoing) {
            return $ongoing;
        }

        // Riwayat: baris perkuliahan terbaru untuk slot ini (untuk status "selesai" di ringkasan)
        return $candidates
            ->sortByDesc(fn (Perkuliahan $p) => [$ts($p), $p->id])
            ->first();
    }

    /**
     * Status sesi perkuliahan untuk satu slot jadwal.
     *
     * @return array{sesi_status: string, sesi_status_label: string}
     */
    private function sesiStatusForPerkuliahan(?Perkuliahan $p): array
    {
        if ($p === null) {
            return [
                'sesi_status' => 'belum_mulai',
                'sesi_status_label' => 'Belum dimulai',
            ];
        }

        $mulai = $p->waktu_mulai !== null && trim((string) $p->waktu_mulai) !== '';
        $selesai = $p->waktu_selesai !== null && trim((string) $p->waktu_selesai) !== '';

        if (! $mulai) {
            return [
                'sesi_status' => 'belum_mulai',
                'sesi_status_label' => 'Belum dimulai',
            ];
        }

        if (! $selesai) {
            return [
                'sesi_status' => 'sedang_berlangsung',
                'sesi_status_label' => 'Sedang berlangsung',
            ];
        }

        return [
            'sesi_status' => 'selesai',
            'sesi_status_label' => 'Selesai',
        ];
    }

    /**
     * Jam dan tanggal tampilan: dari perkuliahan jika ada baris sesi; jika tidak, dari jadwal.
     *
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private function applyWaktuTampilanDariPerkuliahan(array $slot, ?Perkuliahan $p): array
    {
        if ($p === null) {
            return $slot;
        }

        if ($p->waktu_mulai) {
            $mulai = Carbon::parse($p->waktu_mulai);
            $slot['jam_mulai'] = $mulai->format('H:i:s');
            $slot['tanggal'] = $p->tanggal
                ? $p->tanggal->format('Y-m-d')
                : $mulai->format('Y-m-d');
        }

        if ($p->waktu_selesai) {
            $slot['jam_selesai'] = Carbon::parse($p->waktu_selesai)->format('H:i:s');
        } elseif ($p->waktu_mulai) {
            // Sesi sudah ada tetapi belum diakhiri — tidak pakai jam selesai dari jadwal
            $slot['jam_selesai'] = null;
        }

        return $slot;
    }

    /**
     * @param  iterable<int, Jadwal>  $jadwals
     * @return list<array<string, mixed>>
     */
    private function formatJadwalSlotsWithSesi(iterable $jadwals, Collection $perkuliahanRows): array
    {
        $base = $this->formatJadwalSlots($jadwals);
        $byId = collect($jadwals)->keyBy('id');

        return collect($base)
            ->map(function (array $slot) use ($byId, $perkuliahanRows) {
                /** @var Jadwal|null $j */
                $j = $byId->get($slot['id']);
                $p = $j ? $this->findPerkuliahanForJadwalSlot($j, $perkuliahanRows) : null;
                $status = $this->sesiStatusForPerkuliahan($p);
                $slot = $this->applyWaktuTampilanDariPerkuliahan($slot, $p);

                return array_merge($slot, $status);
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeKelasForDosen(Kelas $kelas): array
    {
        $km = $kelas->kurikulumMatkul;
        $m = $km?->matkul;

        return [
            'id' => $kelas->id,
            'nama' => $kelas->kode,
            'kode' => $kelas->kode,
            'kurikulum_matkul' => $km ? [
                'id' => $km->id,
                'kode_matkul' => $km->kode_matkul ?? $m?->kode,
                'nama_matkul' => $km->nama_matkul ?? $m?->nama,
                'nama_matkul_en' => $km->nama_matkul_en,
                'sks' => $km->sks ?? $m?->sks,
                'matkul' => $m ? [
                    'id' => $m->id,
                    'kode' => $m->kode,
                    'nama' => $m->nama,
                    'sks' => $m->sks,
                ] : null,
                'kurikulum' => $km->kurikulum ? [
                    'id' => $km->kurikulum->id,
                    'nama' => $km->kurikulum->nama,
                ] : null,
            ] : null,
            'prodi' => $kelas->prodi ? [
                'id' => $kelas->prodi->id,
                'nama' => $kelas->prodi->nama,
                'jenjang' => $kelas->prodi->jenjang ? [
                    'id' => $kelas->prodi->jenjang->id,
                    'nama' => $kelas->prodi->jenjang->nama,
                ] : null,
            ] : null,
            'semester' => $kelas->semester ? [
                'id' => $kelas->semester->id,
                'kode' => $kelas->semester->kode,
                'nama' => $kelas->semester->nama,
            ] : null,
            'kelompok_kelas' => $kelas->kelompokKelas ? [
                'id' => $kelas->kelompokKelas->id,
                'nama' => $kelas->kelompokKelas->nama ?? null,
            ] : null,
        ];
    }

    /**
     * Kelas di mana dosen adalah penanggung jawab mata kuliah (kelas_dosen.is_pic = true), untuk RPS.
     */
    public function getKelasSebagaiPicUntukRps(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $idSemesterParam = $request->query('id_semester');
        if ($idSemesterParam !== null && $idSemesterParam !== '') {
            $idSemesterFilter = (int) $idSemesterParam;
        } else {
            $activeSemester = Semester::where('is_active', true)->first();
            $idSemesterFilter = $activeSemester ? (int) $activeSemester->id : null;
        }

        $kelasIds = KelasDosen::where('id_dosen', $dosen->id)
            ->where('is_pic', true)
            ->whereNull('deleted_at')
            ->pluck('id_kelas')
            ->unique()
            ->values()
            ->all();

        if ($kelasIds === []) {
            return response()->json(['data' => []]);
        }

        $kelasQuery = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
        ])
            ->whereIn('id', $kelasIds)
            ->whereNull('deleted_at');

        if ($idSemesterFilter !== null) {
            $kelasQuery->where('id_semester', $idSemesterFilter);
        }

        $kelasCollection = $kelasQuery->get()
            ->sortBy(function (Kelas $k) {
                $semId = (int) ($k->id_semester ?? 0);
                $kode = $k->kurikulumMatkul?->kode_matkul ?? $k->kurikulumMatkul?->matkul?->kode ?? '';

                return [-$semId, $kode, $k->id];
            })
            ->values();

        $data = $kelasCollection->map(fn (Kelas $kelas) => $this->serializeKelasForDosen($kelas))->all();

        return response()->json(['data' => $data]);
    }

    private function dosenIsPicForKelasRps(Dosen $dosen, int $kelasId): bool
    {
        return KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelasId)
            ->where('is_pic', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpsCplForDosen(RpsCpl $c): array
    {
        return [
            'id' => $c->id,
            'id_rps' => $c->id_rps,
            'cpl' => $c->cpl,
            'cpl_en' => $c->cpl_en,
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpsSubcpmkForDosen(RpsSubcpmk $s): array
    {
        return [
            'id' => $s->id,
            'id_cpmk' => $s->id_cpmk,
            'subcpmk' => $s->subcpmk,
            'subcpmk_en' => $s->subcpmk_en,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpsCpmkForDosen(RpsCpmk $c): array
    {
        $c->loadMissing('rpsSubcpmk');

        return [
            'id' => $c->id,
            'id_rps' => $c->id_rps,
            'cpmk' => $c->cpmk,
            'cpmk_en' => $c->cpmk_en,
            'updated_at' => $c->updated_at?->toIso8601String(),
            'subcpmk' => $c->rpsSubcpmk->map(fn (RpsSubcpmk $s) => $this->serializeRpsSubcpmkForDosen($s))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpsPembelajaranForDosen(RpsPembelajaran $p): array
    {
        return [
            'id' => $p->id,
            'id_rps' => $p->id_rps,
            'urutan_pertemuan' => $p->urutan_pertemuan,
            'sub_cpmk' => $p->sub_cpmk,
            'indikator_penilaian' => $p->indikator_penilaian,
            'bentuk_kriteria_penilaian' => $p->bentuk_kriteria_penilaian,
            'pembelajaran_sinkron' => $p->pembelajaran_sinkron,
            'pembelajaran_asinkron' => $p->pembelajaran_asinkron,
            'materi' => $p->materi,
            'materi_en' => $p->materi_en,
            'bobot' => $p->bobot === null ? null : (float) $p->bobot,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    private function serializeRpsForDosen(Rps $r): array
    {
        $r->loadMissing(['rpsCpl', 'rpsCpmk.rpsSubcpmk', 'rpsPembelajaran']);

        return [
            'id' => $r->id,
            'id_kelas' => $r->id_kelas,
            'deskripsi_matkul' => $r->deskripsi_matkul,
            'deskripsi_matkul_en' => $r->deskripsi_matkul_en,
            'materi_kuliah' => $r->materi_kuliah,
            'model_pembelajaran' => $r->model_pembelajaran,
            'pustaka_utama' => $r->pustaka_utama,
            'pustaka_pendukung' => $r->pustaka_pendukung,
            'media_perangkat_lunak' => $r->media_perangkat_lunak,
            'media_perangkat_keras' => $r->media_perangkat_keras,
            'tanggal_penyusunan' => $r->tanggal_penyusunan?->toIso8601String(),
            'file_rps' => $r->file_rps,
            'created_by' => $r->created_by,
            'verified_by' => $r->verified_by,
            'approved_by' => $r->approved_by,
            'verified_at' => $r->verified_at?->toIso8601String(),
            'approved_at' => $r->approved_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
            'cpl' => $r->rpsCpl->map(fn (RpsCpl $c) => $this->serializeRpsCplForDosen($c))->values()->all(),
            'cpmk' => $r->rpsCpmk->map(fn (RpsCpmk $c) => $this->serializeRpsCpmkForDosen($c))->values()->all(),
            'pembelajaran' => $r->rpsPembelajaran->map(fn (RpsPembelajaran $p) => $this->serializeRpsPembelajaranForDosen($p))->values()->all(),
        ];
    }

    /**
     * RPS per kelas (hanya PIC mata kuliah).
     */
    public function getRpsByKelas(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengakses RPS kelas ini'], 403);
        }

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
            'rps.rpsCpl',
            'rps.rpsCpmk.rpsSubcpmk',
            'rps.rpsPembelajaran',
        ])
            ->whereNull('deleted_at')
            ->find($kelasId);

        if (! $kelas) {
            return response()->json(['message' => 'Kelas tidak ditemukan'], 404);
        }

        $rps = $kelas->rps;

        return response()->json([
            'kelas' => $this->serializeKelasForDosen($kelas),
            'rps' => $rps ? $this->serializeRpsForDosen($rps) : null,
        ]);
    }

    /**
     * Apakah semester $candidate secara kronologis lebih awal daripada $pivot (semester kelas saat ini).
     */
    private function semesterStrictlyOlderThan(?Semester $candidate, Semester $pivot): bool
    {
        if (! $candidate) {
            return false;
        }
        if ($candidate->tanggal_mulai && $pivot->tanggal_mulai) {
            if ($candidate->tanggal_mulai->lt($pivot->tanggal_mulai)) {
                return true;
            }
            if ($candidate->tanggal_mulai->eq($pivot->tanggal_mulai)) {
                return strcmp((string) $candidate->kode, (string) $pivot->kode) < 0;
            }

            return false;
        }

        return strcmp((string) $candidate->kode, (string) $pivot->kode) < 0;
    }

    /**
     * @return array<int, int>
     */
    private function idsSemesterLebihAwalDari(Semester $pivot): array
    {
        return Semester::query()
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (Semester $s) => $this->semesterStrictlyOlderThan($s, $pivot))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function hapusIsiRelasiRps(Rps $rps): void
    {
        $rps->load(['rpsPembelajaran', 'rpsCpmk.rpsSubcpmk', 'rpsCpl']);
        foreach ($rps->rpsPembelajaran as $row) {
            $row->delete();
        }
        foreach ($rps->rpsCpmk as $cpmk) {
            foreach ($cpmk->rpsSubcpmk as $sub) {
                $sub->delete();
            }
            $cpmk->delete();
        }
        foreach ($rps->rpsCpl as $cpl) {
            $cpl->delete();
        }
    }

    /**
     * Daftar kelas (semester lebih awal, mata kuliah sama, Anda PIC) yang punya RPS — untuk modal duplikat.
     */
    public function getRpsSumberDuplikat(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengakses RPS kelas ini'], 403);
        }

        $target = Kelas::with(['semester'])
            ->whereNull('deleted_at')
            ->find($kelasId);
        if (! $target) {
            return response()->json(['message' => 'Kelas tidak ditemukan'], 404);
        }

        $pivotSem = $target->semester;
        if (! $pivotSem) {
            return response()->json(['message' => 'Kelas belum memiliki semester akademik'], 422);
        }

        $kmId = (int) ($target->id_kurikulum_matkul ?? 0);
        if ($kmId === 0) {
            return response()->json(['message' => 'Kelas tidak memiliki pemetaan kurikulum mata kuliah'], 422);
        }

        $olderSemIds = $this->idsSemesterLebihAwalDari($pivotSem);
        if ($olderSemIds === []) {
            return response()->json(['data' => []]);
        }

        $picKelasIds = KelasDosen::where('id_dosen', $dosen->id)
            ->where('is_pic', true)
            ->whereNull('deleted_at')
            ->pluck('id_kelas')
            ->unique()
            ->values()
            ->all();

        if ($picKelasIds === []) {
            return response()->json(['data' => []]);
        }

        $rows = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
        ])
            ->whereNull('deleted_at')
            ->where('id_kurikulum_matkul', $kmId)
            ->where('id', '!=', $kelasId)
            ->whereIn('id_semester', $olderSemIds)
            ->whereIn('id', $picKelasIds)
            ->whereHas('rps')
            ->whereHas('semester', fn ($q) => $q->whereNull('deleted_at'))
            ->get()
            ->sortByDesc(function (Kelas $k) {
                $kodeSem = $k->semester?->kode ?? '';

                return [$kodeSem, $k->kode ?? '', $k->id];
            })
            ->values();

        $data = $rows->map(fn (Kelas $kelas) => $this->serializeKelasForDosen($kelas))->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Salin isi RPS dari kelas sumber (semester lebih awal, MK sama, Anda PIC) ke kelas tujuan.
     */
    public function duplikatRpsDariKelasLain(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah RPS kelas ini'], 403);
        }

        $validated = $request->validate([
            'id_kelas_sumber' => ['required', 'integer', 'not_in:'.$kelasId],
        ]);
        $sourceKelasId = (int) $validated['id_kelas_sumber'];

        if (! $this->dosenIsPicForKelasRps($dosen, $sourceKelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengakses RPS kelas sumber'], 403);
        }

        $target = Kelas::with(['semester'])->whereNull('deleted_at')->find($kelasId);
        $source = Kelas::with(['semester'])->whereNull('deleted_at')->find($sourceKelasId);
        if (! $target || ! $source) {
            return response()->json(['message' => 'Kelas tidak ditemukan'], 404);
        }

        if ((int) $target->id_kurikulum_matkul !== (int) $source->id_kurikulum_matkul) {
            return response()->json(['message' => 'Mata kuliah kelas sumber tidak sama dengan kelas ini'], 422);
        }

        $pivotSem = $target->semester;
        $srcSem = $source->semester;
        if (! $pivotSem || ! $srcSem) {
            return response()->json(['message' => 'Semester akademik kelas tidak lengkap'], 422);
        }

        if (! $this->semesterStrictlyOlderThan($srcSem, $pivotSem)) {
            return response()->json(['message' => 'RPS hanya boleh disalin dari kelas pada semester yang lebih awal'], 422);
        }

        $sourceRps = Rps::with(['rpsCpl', 'rpsCpmk.rpsSubcpmk', 'rpsPembelajaran'])
            ->where('id_kelas', $sourceKelasId)
            ->first();
        if (! $sourceRps) {
            return response()->json(['message' => 'Kelas sumber belum memiliki data RPS'], 422);
        }

        try {
            DB::transaction(function () use ($user, $kelasId, $sourceRps): void {
                Kelas::whereKey($kelasId)->lockForUpdate()->first();

                $targetRps = Rps::where('id_kelas', $kelasId)->lockForUpdate()->first();
                if ($targetRps) {
                    $this->hapusIsiRelasiRps($targetRps);
                } else {
                    $targetRps = new Rps;
                    $targetRps->id_kelas = $kelasId;
                    $targetRps->created_by = $user->name ?? (string) $user->id;
                }

                foreach ([
                    'deskripsi_matkul',
                    'deskripsi_matkul_en',
                    'materi_kuliah',
                    'model_pembelajaran',
                    'pustaka_utama',
                    'pustaka_pendukung',
                    'media_perangkat_lunak',
                    'media_perangkat_keras',
                    'tanggal_penyusunan',
                ] as $field) {
                    $targetRps->{$field} = $sourceRps->{$field};
                }

                $targetRps->id_kelas = $kelasId;
                $targetRps->file_rps = null;
                $targetRps->verified_by = null;
                $targetRps->approved_by = null;
                $targetRps->verified_at = null;
                $targetRps->approved_at = null;
                $targetRps->save();

                foreach ($sourceRps->rpsCpl as $c) {
                    RpsCpl::create([
                        'id_rps' => $targetRps->id,
                        'cpl' => $c->cpl,
                        'cpl_en' => $c->cpl_en,
                    ]);
                }

                foreach ($sourceRps->rpsCpmk as $c) {
                    $nc = RpsCpmk::create([
                        'id_rps' => $targetRps->id,
                        'cpmk' => $c->cpmk,
                        'cpmk_en' => $c->cpmk_en,
                    ]);
                    foreach ($c->rpsSubcpmk as $s) {
                        RpsSubcpmk::create([
                            'id_cpmk' => $nc->id,
                            'subcpmk' => $s->subcpmk,
                            'subcpmk_en' => $s->subcpmk_en,
                        ]);
                    }
                }

                foreach ($sourceRps->rpsPembelajaran as $p) {
                    RpsPembelajaran::create([
                        'id_rps' => $targetRps->id,
                        'urutan_pertemuan' => $p->urutan_pertemuan,
                        'sub_cpmk' => $p->sub_cpmk,
                        'indikator_penilaian' => $p->indikator_penilaian,
                        'bentuk_kriteria_penilaian' => $p->bentuk_kriteria_penilaian,
                        'pembelajaran_sinkron' => $p->pembelajaran_sinkron,
                        'pembelajaran_asinkron' => $p->pembelajaran_asinkron,
                        'materi' => $p->materi,
                        'materi_en' => $p->materi_en,
                        'bobot' => $p->bobot,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal menduplikasi RPS. Coba lagi atau hubungi administrator.'], 500);
        }

        $fresh = Rps::with(['rpsCpl', 'rpsCpmk.rpsSubcpmk', 'rpsPembelajaran'])
            ->where('id_kelas', $kelasId)
            ->first();

        return response()->json([
            'message' => 'RPS berhasil disalin dari kelas sumber',
            'rps' => $fresh ? $this->serializeRpsForDosen($fresh) : null,
        ]);
    }

    /**
     * Tambah baris CPL pada RPS kelas (hanya PIC; RPS harus sudah ada).
     */
    public function storeRpsCpl(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah RPS kelas ini'], 403);
        }

        $rps = Rps::where('id_kelas', $kelasId)->first();
        if (! $rps) {
            return response()->json([
                'message' => 'Simpan bagian deskripsi RPS terlebih dahulu agar baris RPS tersedia, lalu tambahkan CPL.',
            ], 422);
        }

        $validated = $request->validate([
            'cpl' => ['nullable', 'string'],
            'cpl_en' => ['nullable', 'string'],
        ]);

        $cplText = isset($validated['cpl']) ? trim((string) $validated['cpl']) : '';
        $cplEnText = isset($validated['cpl_en']) ? trim((string) $validated['cpl_en']) : '';
        if ($cplText === '' && $cplEnText === '') {
            return response()->json(['message' => 'Isi teks CPL (Bahasa Indonesia) dan/atau CPL (English).'], 422);
        }

        $row = RpsCpl::create([
            'id_rps' => $rps->id,
            'cpl' => $cplText !== '' ? $cplText : null,
            'cpl_en' => $cplEnText !== '' ? $cplEnText : null,
        ]);

        return response()->json([
            'message' => 'CPL berhasil ditambahkan',
            'cpl' => $this->serializeRpsCplForDosen($row),
        ], 201);
    }

    /**
     * Perbarui baris CPL (hanya PIC pemilik RPS kelas).
     */
    public function updateRpsCpl(Request $request, int $cplId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $row = RpsCpl::with('rps')->find($cplId);
        if (! $row || ! $row->rps) {
            return response()->json(['message' => 'Data CPL tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $row->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah CPL ini'], 403);
        }

        $validated = $request->validate([
            'cpl' => ['nullable', 'string'],
            'cpl_en' => ['nullable', 'string'],
        ]);

        $cplText = trim((string) ($validated['cpl'] ?? ''));
        $cplEnText = trim((string) ($validated['cpl_en'] ?? ''));
        $row->cpl = $cplText !== '' ? $cplText : null;
        $row->cpl_en = $cplEnText !== '' ? $cplEnText : null;

        if ($row->cpl === null && $row->cpl_en === null) {
            return response()->json(['message' => 'Minimal satu kolom CPL (ID atau EN) harus diisi.'], 422);
        }

        $row->save();

        return response()->json([
            'message' => 'CPL berhasil diperbarui',
            'cpl' => $this->serializeRpsCplForDosen($row->fresh()),
        ]);
    }

    /**
     * Hapus baris CPL (soft delete, hanya PIC pemilik RPS kelas).
     */
    public function destroyRpsCpl(Request $request, int $cplId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $row = RpsCpl::with('rps')->find($cplId);
        if (! $row || ! $row->rps) {
            return response()->json(['message' => 'Data CPL tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $row->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak menghapus CPL ini'], 403);
        }

        $row->delete();

        return response()->json(['message' => 'CPL berhasil dihapus']);
    }

    /**
     * Tambah CPMK pada RPS kelas (hanya PIC; RPS harus sudah ada).
     */
    public function storeRpsCpmk(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah RPS kelas ini'], 403);
        }

        $rps = Rps::where('id_kelas', $kelasId)->first();
        if (! $rps) {
            return response()->json([
                'message' => 'Simpan bagian deskripsi RPS terlebih dahulu agar baris RPS tersedia.',
            ], 422);
        }

        $validated = $request->validate([
            'cpmk' => ['nullable', 'string'],
            'cpmk_en' => ['nullable', 'string'],
        ]);

        $cpmkIdText = trim((string) ($validated['cpmk'] ?? ''));
        $cpmkEnText = trim((string) ($validated['cpmk_en'] ?? ''));
        if ($cpmkIdText === '' && $cpmkEnText === '') {
            return response()->json(['message' => 'Isi teks CPMK (Bahasa Indonesia) dan/atau CPMK (English).'], 422);
        }

        $row = RpsCpmk::create([
            'id_rps' => $rps->id,
            'cpmk' => $cpmkIdText !== '' ? $cpmkIdText : null,
            'cpmk_en' => $cpmkEnText !== '' ? $cpmkEnText : null,
        ]);

        return response()->json([
            'message' => 'CPMK berhasil ditambahkan',
            'cpmk' => $this->serializeRpsCpmkForDosen($row->fresh()),
        ], 201);
    }

    public function updateRpsCpmk(Request $request, int $cpmkId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $row = RpsCpmk::with('rps')->find($cpmkId);
        if (! $row || ! $row->rps) {
            return response()->json(['message' => 'Data CPMK tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $row->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah CPMK ini'], 403);
        }

        $validated = $request->validate([
            'cpmk' => ['nullable', 'string'],
            'cpmk_en' => ['nullable', 'string'],
        ]);

        $cpmkIdText = trim((string) ($validated['cpmk'] ?? ''));
        $cpmkEnText = trim((string) ($validated['cpmk_en'] ?? ''));
        $row->cpmk = $cpmkIdText !== '' ? $cpmkIdText : null;
        $row->cpmk_en = $cpmkEnText !== '' ? $cpmkEnText : null;

        if ($row->cpmk === null && $row->cpmk_en === null) {
            return response()->json(['message' => 'Minimal satu kolom CPMK (ID atau EN) harus diisi.'], 422);
        }

        $row->save();

        return response()->json([
            'message' => 'CPMK berhasil diperbarui',
            'cpmk' => $this->serializeRpsCpmkForDosen($row->fresh()),
        ]);
    }

    public function destroyRpsCpmk(Request $request, int $cpmkId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $row = RpsCpmk::with('rps')->find($cpmkId);
        if (! $row || ! $row->rps) {
            return response()->json(['message' => 'Data CPMK tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $row->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak menghapus CPMK ini'], 403);
        }

        foreach (RpsSubcpmk::where('id_cpmk', $row->id)->get() as $s) {
            $s->delete();
        }
        $row->delete();

        return response()->json(['message' => 'CPMK dan sub-CPMK terkait berhasil dihapus']);
    }

    public function storeRpsSubcpmk(Request $request, int $cpmkId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $cpmk = RpsCpmk::with('rps')->find($cpmkId);
        if (! $cpmk || ! $cpmk->rps) {
            return response()->json(['message' => 'Data CPMK tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $cpmk->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak menambah sub-CPMK ini'], 403);
        }

        $validated = $request->validate([
            'subcpmk' => ['nullable', 'string'],
            'subcpmk_en' => ['nullable', 'string'],
        ]);

        $subIdText = trim((string) ($validated['subcpmk'] ?? ''));
        $subEnText = trim((string) ($validated['subcpmk_en'] ?? ''));
        if ($subIdText === '' && $subEnText === '') {
            return response()->json(['message' => 'Isi teks sub-CPMK (Bahasa Indonesia) dan/atau (English).'], 422);
        }

        $sub = RpsSubcpmk::create([
            'id_cpmk' => $cpmk->id,
            'subcpmk' => $subIdText !== '' ? $subIdText : null,
            'subcpmk_en' => $subEnText !== '' ? $subEnText : null,
        ]);

        return response()->json([
            'message' => 'Sub-CPMK berhasil ditambahkan',
            'subcpmk' => $this->serializeRpsSubcpmkForDosen($sub),
        ], 201);
    }

    public function updateRpsSubcpmk(Request $request, int $subcpmkId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $sub = RpsSubcpmk::with('rpsCpmk.rps')->find($subcpmkId);
        if (! $sub || ! $sub->rpsCpmk || ! $sub->rpsCpmk->rps) {
            return response()->json(['message' => 'Data sub-CPMK tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $sub->rpsCpmk->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah sub-CPMK ini'], 403);
        }

        $validated = $request->validate([
            'subcpmk' => ['nullable', 'string'],
            'subcpmk_en' => ['nullable', 'string'],
        ]);

        $subIdText = trim((string) ($validated['subcpmk'] ?? ''));
        $subEnText = trim((string) ($validated['subcpmk_en'] ?? ''));
        $sub->subcpmk = $subIdText !== '' ? $subIdText : null;
        $sub->subcpmk_en = $subEnText !== '' ? $subEnText : null;

        if ($sub->subcpmk === null && $sub->subcpmk_en === null) {
            return response()->json(['message' => 'Minimal satu kolom sub-CPMK (ID atau EN) harus diisi.'], 422);
        }

        $sub->save();

        return response()->json([
            'message' => 'Sub-CPMK berhasil diperbarui',
            'subcpmk' => $this->serializeRpsSubcpmkForDosen($sub->fresh()),
        ]);
    }

    public function destroyRpsSubcpmk(Request $request, int $subcpmkId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $sub = RpsSubcpmk::with('rpsCpmk.rps')->find($subcpmkId);
        if (! $sub || ! $sub->rpsCpmk || ! $sub->rpsCpmk->rps) {
            return response()->json(['message' => 'Data sub-CPMK tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $sub->rpsCpmk->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak menghapus sub-CPMK ini'], 403);
        }

        $sub->delete();

        return response()->json(['message' => 'Sub-CPMK berhasil dihapus']);
    }

    private function rulesRpsPembelajaran(): array
    {
        return [
            'urutan_pertemuan' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sub_cpmk' => ['nullable', 'string'],
            'indikator_penilaian' => ['nullable', 'string'],
            'bentuk_kriteria_penilaian' => ['nullable', 'string'],
            'pembelajaran_sinkron' => ['nullable', 'string'],
            'pembelajaran_asinkron' => ['nullable', 'string'],
            'materi' => ['nullable', 'string'],
            'materi_en' => ['nullable', 'string'],
            'bobot' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyValidatedToRpsPembelajaran(RpsPembelajaran $row, array $validated): void
    {
        $row->urutan_pertemuan = $validated['urutan_pertemuan'] ?? null;
        $row->sub_cpmk = $validated['sub_cpmk'] ?? null;
        $row->indikator_penilaian = $validated['indikator_penilaian'] ?? null;
        $row->bentuk_kriteria_penilaian = $validated['bentuk_kriteria_penilaian'] ?? null;
        $row->pembelajaran_sinkron = $validated['pembelajaran_sinkron'] ?? null;
        $row->pembelajaran_asinkron = $validated['pembelajaran_asinkron'] ?? null;
        $row->materi = $validated['materi'] ?? null;
        $row->materi_en = $validated['materi_en'] ?? null;
        $row->bobot = $validated['bobot'] ?? null;
    }

    public function storeRpsPembelajaran(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah RPS kelas ini'], 403);
        }

        $rps = Rps::where('id_kelas', $kelasId)->first();
        if (! $rps) {
            return response()->json([
                'message' => 'Simpan bagian deskripsi RPS terlebih dahulu agar baris RPS tersedia.',
            ], 422);
        }

        $validated = $request->validate($this->rulesRpsPembelajaran());

        $row = new RpsPembelajaran;
        $row->id_rps = $rps->id;
        $this->applyValidatedToRpsPembelajaran($row, $validated);
        $row->save();

        return response()->json([
            'message' => 'Rincian pembelajaran berhasil ditambahkan',
            'pembelajaran' => $this->serializeRpsPembelajaranForDosen($row->fresh()),
        ], 201);
    }

    public function updateRpsPembelajaran(Request $request, int $pembelajaranId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $row = RpsPembelajaran::with('rps')->find($pembelajaranId);
        if (! $row || ! $row->rps) {
            return response()->json(['message' => 'Data rincian pembelajaran tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $row->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah data ini'], 403);
        }

        $validated = $request->validate($this->rulesRpsPembelajaran());
        $this->applyValidatedToRpsPembelajaran($row, $validated);
        $row->save();

        return response()->json([
            'message' => 'Rincian pembelajaran berhasil diperbarui',
            'pembelajaran' => $this->serializeRpsPembelajaranForDosen($row->fresh()),
        ]);
    }

    public function destroyRpsPembelajaran(Request $request, int $pembelajaranId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $row = RpsPembelajaran::with('rps')->find($pembelajaranId);
        if (! $row || ! $row->rps) {
            return response()->json(['message' => 'Data rincian pembelajaran tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, (int) $row->rps->id_kelas)) {
            return response()->json(['message' => 'Anda tidak berhak menghapus data ini'], 403);
        }

        $row->delete();

        return response()->json(['message' => 'Rincian pembelajaran berhasil dihapus']);
    }

    /**
     * Buat atau perbarui RPS untuk kelas (hanya PIC).
     */
    public function upsertRpsByKelas(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah RPS kelas ini'], 403);
        }

        $kelas = Kelas::whereNull('deleted_at')->find($kelasId);
        if (! $kelas) {
            return response()->json(['message' => 'Kelas tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'deskripsi_matkul' => ['nullable', 'string'],
            'deskripsi_matkul_en' => ['nullable', 'string'],
            'materi_kuliah' => ['nullable', 'string'],
            'model_pembelajaran' => ['nullable', 'string'],
            'pustaka_utama' => ['nullable', 'string'],
            'pustaka_pendukung' => ['nullable', 'string'],
            'media_perangkat_lunak' => ['nullable', 'string'],
            'media_perangkat_keras' => ['nullable', 'string'],
            'tanggal_penyusunan' => ['nullable', 'date'],
        ]);

        $rps = Rps::firstOrNew(['id_kelas' => $kelasId]);
        $wasRecentlyCreated = ! $rps->exists;

        foreach ([
            'deskripsi_matkul',
            'deskripsi_matkul_en',
            'materi_kuliah',
            'model_pembelajaran',
            'pustaka_utama',
            'pustaka_pendukung',
            'media_perangkat_lunak',
            'media_perangkat_keras',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $rps->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('tanggal_penyusunan', $validated)) {
            $rps->tanggal_penyusunan = $validated['tanggal_penyusunan'] ?? null;
        }

        if ($wasRecentlyCreated) {
            $rps->created_by = $user->name ?? (string) $user->id;
        }

        $rps->save();

        return response()->json([
            'message' => $wasRecentlyCreated ? 'RPS berhasil dibuat' : 'RPS berhasil diperbarui',
            'rps' => $this->serializeRpsForDosen($rps->fresh()),
        ]);
    }

    /**
     * Kelas yang diampu dosen (tabel kelas_dosen), difilter semester akademik (kelas.id_semester).
     * Default: semester berstatus aktif.
     */
    public function getKelasAmpu(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $idSemester = $request->query('id_semester');
        if ($idSemester === null || $idSemester === '') {
            $active = Semester::where('is_active', true)->first();
            $idSemester = $active?->id;
        } else {
            $idSemester = (int) $idSemester;
        }

        $kelasDosenRows = KelasDosen::where('id_dosen', $dosen->id)
            ->whereNull('deleted_at')
            ->get();

        $kelasIds = $kelasDosenRows->pluck('id_kelas')->unique()->values()->all();
        /** @var Collection<int, KelasDosen> $picByKelas */
        $picByKelas = $kelasDosenRows->keyBy('id_kelas');

        if ($kelasIds === []) {
            $selectedSemester = $idSemester ? Semester::find($idSemester) : Semester::where('is_active', true)->first();

            return response()->json([
                'semester' => $selectedSemester ? [
                    'id' => $selectedSemester->id,
                    'kode' => $selectedSemester->kode,
                    'nama' => $selectedSemester->nama,
                ] : null,
                'data' => [],
            ]);
        }

        $query = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
            'jadwal' => function ($q): void {
                $q->whereNull('deleted_at')
                    ->with(['ruangan', 'jenisKuliah', 'dosen.dosen'])
                    ->orderBy('hari')
                    ->orderBy('jam_mulai');
            },
        ])
            ->whereIn('id', $kelasIds)
            ->whereNull('deleted_at');

        if ($idSemester) {
            $query->where('id_semester', $idSemester);
        }

        $kelasCollection = $query->get()->sortBy(function (Kelas $k) {
            $kode = $k->kurikulumMatkul?->kode_matkul ?? $k->kurikulumMatkul?->matkul?->kode ?? '';

            return $kode.'-'.$k->id;
        })->values();

        $selectedSemester = $idSemester
            ? Semester::find($idSemester)
            : Semester::where('is_active', true)->first();

        $data = $kelasCollection->map(function (Kelas $kelas) use ($picByKelas) {
            /** @var KelasDosen|null $row */
            $row = $picByKelas->get($kelas->id);

            return [
                'kelas' => $this->serializeKelasForDosen($kelas),
                'is_pic' => (bool) ($row?->is_pic ?? false),
                'jadwal' => $this->formatJadwalSlots($kelas->jadwal),
            ];
        })->values()->all();

        return response()->json([
            'semester' => $selectedSemester ? [
                'id' => $selectedSemester->id,
                'kode' => $selectedSemester->kode,
                'nama' => $selectedSemester->nama,
            ] : null,
            'data' => $data,
        ]);
    }

    /**
     * Rincian jadwal slot untuk satu kelas yang diampu (kelas_dosen).
     */
    public function getRincianJadwalKelasAmpu(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $allowed = KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $allowed) {
            return response()->json(['message' => 'Anda tidak mengampu kelas ini'], 403);
        }

        $idSemester = $request->query('id_semester');
        $idSemester = $idSemester !== null && $idSemester !== '' ? (int) $idSemester : null;

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
            'jadwal' => function ($q): void {
                $q->whereNull('deleted_at')
                    ->with(['ruangan', 'jenisKuliah', 'dosen.dosen'])
                    ->orderBy('hari')
                    ->orderBy('jam_mulai');
            },
        ])
            ->whereNull('deleted_at')
            ->find($kelasId);

        if (! $kelas) {
            return response()->json(['message' => 'Kelas tidak ditemukan'], 404);
        }

        if ($idSemester !== null && (int) $kelas->id_semester !== $idSemester) {
            return response()->json(['message' => 'Kelas tidak termasuk semester yang dipilih'], 422);
        }

        $row = KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->first();

        $jadwalIds = $kelas->jadwal->pluck('id')->filter()->values()->all();
        $perkuliahanRows = $jadwalIds === []
            ? collect()
            : Perkuliahan::whereIn('id_jadwal', $jadwalIds)->whereNull('deleted_at')->get();

        $jumlahMahasiswa = Krs::where('id_kelas', $kelasId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'kelas' => $this->serializeKelasForDosen($kelas),
            'is_pic' => (bool) ($row?->is_pic ?? false),
            'semester' => $kelas->semester ? [
                'id' => $kelas->semester->id,
                'kode' => $kelas->semester->kode,
                'nama' => $kelas->semester->nama,
            ] : null,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'jadwal' => $this->formatJadwalSlotsWithSesi($kelas->jadwal, $perkuliahanRows),
        ]);
    }

    /**
     * Mahasiswa yang mengambil kelas ini (KRS disetujui), untuk dosen pengampu.
     * Format baris sama dengan KehadiranController::getByPerkuliahan (kehadiran selalu null).
     */
    public function getMahasiswaKrsKelasAmpu(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $allowed = KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $allowed) {
            return response()->json(['message' => 'Anda tidak mengampu kelas ini'], 403);
        }

        $idSemester = $request->query('id_semester');
        $idSemester = $idSemester !== null && $idSemester !== '' ? (int) $idSemester : null;

        $kelas = Kelas::whereNull('deleted_at')->find($kelasId);
        if (! $kelas) {
            return response()->json(['message' => 'Kelas tidak ditemukan'], 404);
        }

        if ($idSemester !== null && (int) $kelas->id_semester !== $idSemester) {
            return response()->json(['message' => 'Kelas tidak termasuk semester yang dipilih'], 422);
        }

        $mahasiswaList = Krs::with([
            'mahasiswa:id,nim,nama',
            'mahasiswa.prodi:id,nama',
        ])
            ->where('id_kelas', $kelasId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn ($krs) => $krs->mahasiswa !== null)
            ->sortBy(fn ($krs) => $krs->mahasiswa->nim ?? '')
            ->values()
            ->map(function ($krs) {
                $m = $krs->mahasiswa;

                return [
                    'id_krs' => $krs->id,
                    'mahasiswa' => [
                        'id' => $m->id,
                        'nim' => $m->nim,
                        'nama' => $m->nama,
                        'prodi' => $m->prodi ? [
                            'id' => $m->prodi->id,
                            'nama' => $m->prodi->nama,
                        ] : null,
                    ],
                    'kehadiran' => null,
                ];
            });

        return response()->json([
            'mahasiswa' => $mahasiswaList,
        ]);
    }

    /**
     * Get jadwal mengajar by dosen ID
     */
    public function getByDosen(Request $request, int $idDosen): JsonResponse
    {
        $idSemester = $request->get('id_semester') ?? $request->get('id_semester_masuk');

        if (! $idSemester) {
            $activeSemester = Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $idSemester = $activeSemester->id;
            }
        }

        $query = JadwalDosen::with([
            'jadwal.kelas.kurikulumMatkul.matkul',
            'jadwal.kelas.kurikulumMatkul.kurikulum',
            'jadwal.kelas.prodi',
            'jadwal.kelas.prodi.jenjang',
            'jadwal.kelas.semester',
            'jadwal.kelas.kelompokKelas',
            'jadwal.ruangan',
            'jadwal.jenisKuliah',
            'jadwal.dosen.dosen',
        ])
            ->where('id_dosen', $idDosen)
            ->where('status', 'active');

        if ($idSemester) {
            $query->whereHas('jadwal.kelas', function ($q) use ($idSemester) {
                $q->where('id_semester', (int) $idSemester);
            });
        }

        $data = $query->get()
            ->sortBy(function ($item) {
                $hari = $item->jadwal->hari ?? '';
                $hariOrder = [
                    'senin' => 1,
                    'selasa' => 2,
                    'rabu' => 3,
                    'kamis' => 4,
                    'jumat' => 5,
                    'sabtu' => 6,
                    'minggu' => 7,
                ];
                $hariNum = $hariOrder[strtolower($hari)] ?? 8;
                $jamMulai = $item->jadwal->jam_mulai ?? '00:00:00';

                return $hariNum * 10000 + str_replace(':', '', $jamMulai);
            })
            ->values();

        return response()->json($data);
    }

    /**
     * Admin: daftar kelas yang diampu dosen lewat tabel kelas_dosen (bukan slot jadwal).
     * Query opsional: id_semester — filter kelas.id_semester (semester berjalan).
     */
    public function getKelasDiampuByDosen(Request $request, int $id): JsonResponse
    {
        if (! Dosen::where('id', $id)->exists()) {
            return response()->json(['message' => 'Dosen tidak ditemukan'], 404);
        }

        $idSemester = $request->query('id_semester');
        $idSemester = ($idSemester !== null && $idSemester !== '') ? (int) $idSemester : null;

        $query = KelasDosen::query()
            ->where('id_dosen', $id)
            ->whereNull('deleted_at')
            ->with([
                'kelas' => function ($q) use ($idSemester) {
                    $q->whereNull('deleted_at')
                        ->with([
                            'kurikulumMatkul.matkul',
                            'kurikulumMatkul.kurikulum',
                            'prodi.jenjang',
                            'semester',
                            'angkatan',
                            'kelompokKelas',
                            'dosenPic',
                        ]);
                    if ($idSemester) {
                        $q->where('id_semester', $idSemester);
                    }
                },
            ])
            ->orderBy('id');

        $rows = $query->get()->filter(fn (KelasDosen $kd) => $kd->kelas !== null)->values();

        $data = $rows->map(function (KelasDosen $kd) {
            $k = $kd->kelas;
            if (! $k) {
                return null;
            }

            return [
                'id' => $kd->id,
                'is_pic' => (bool) ($kd->is_pic ?? false),
                'kelas' => [
                    'id' => $k->id,
                    'kode' => $k->kode,
                    'jml_pertemuan' => $k->jml_pertemuan,
                    'is_mingguan' => (bool) $k->is_mingguan,
                    'kuota' => $k->kuota,
                    'is_active' => (bool) $k->is_active,
                    'kurikulum_matkul' => $k->kurikulumMatkul ? [
                        'id' => $k->kurikulumMatkul->id,
                        'matkul' => $k->kurikulumMatkul->matkul,
                        'kurikulum' => $k->kurikulumMatkul->kurikulum,
                    ] : null,
                    'prodi' => $k->prodi,
                    'semester' => $k->semester,
                    'angkatan' => $k->angkatan,
                    'kelompok_kelas' => $k->kelompokKelas,
                    'dosen_pic' => $k->dosenPic,
                ],
            ];
        })->filter()->values()->all();

        return response()->json($data);
    }

    /**
     * Get jadwal mengajar untuk dosen yang sedang login (jadwal_dosen + filter semester akademik lewat kelas).
     */
    public function getMyJadwal(Request $request): JsonResponse
    {
        $user = $request->user();

        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $idSemester = $request->get('id_semester') ?? $request->get('id_semester_masuk');

        if (! $idSemester) {
            $activeSemester = Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $idSemester = $activeSemester->id;
            }
        }

        $query = JadwalDosen::with([
            'jadwal.kelas.kurikulumMatkul.matkul',
            'jadwal.kelas.kurikulumMatkul.kurikulum',
            'jadwal.kelas.prodi',
            'jadwal.kelas.prodi.jenjang',
            'jadwal.kelas.semester',
            'jadwal.kelas.kelompokKelas',
            'jadwal.ruangan',
            'jadwal.jenisKuliah',
            'jadwal.dosen.dosen',
        ])
            ->where('id_dosen', $dosen->id)
            ->where('status', 'active');

        if ($idSemester) {
            $query->whereHas('jadwal.kelas', function ($q) use ($idSemester) {
                $q->where('id_semester', (int) $idSemester);
            });
        }

        $data = $query->get()
            ->sortBy(function ($item) {
                $hari = $item->jadwal->hari ?? '';
                $hariOrder = [
                    'senin' => 1,
                    'selasa' => 2,
                    'rabu' => 3,
                    'kamis' => 4,
                    'jumat' => 5,
                    'sabtu' => 6,
                    'minggu' => 7,
                ];
                $hariNum = $hariOrder[strtolower($hari)] ?? 8;
                $jamMulai = $item->jadwal->jam_mulai ?? '00:00:00';

                return $hariNum * 10000 + str_replace(':', '', $jamMulai);
            })
            ->values();

        $activeSemester = Semester::where('is_active', true)->first();
        $selectedSemester = $idSemester
            ? Semester::find((int) $idSemester)
            : $activeSemester;

        return response()->json([
            'semester' => $selectedSemester ? [
                'id' => $selectedSemester->id,
                'kode' => $selectedSemester->kode,
                'nama' => $selectedSemester->nama,
            ] : null,
            'data' => $data,
        ]);
    }

    /**
     * PDF Jurnal Perkuliahan untuk satu kelas ampu (format laporan resmi).
     */
    public function downloadJurnalPerkuliahanPdf(Request $request, int $kelasId): StreamedResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            abort(404, 'Data dosen tidak ditemukan');
        }

        $allowed = KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $allowed) {
            abort(403, 'Anda tidak mengampu kelas ini');
        }

        $idSemester = $request->query('id_semester');
        $idSemester = $idSemester !== null && $idSemester !== '' ? (int) $idSemester : null;

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'prodi',
            'semester',
            'kelompokKelas',
            'dosenPic',
            'jadwal' => function ($q): void {
                $q->whereNull('deleted_at')
                    ->with(['ruangan', 'jenisKuliah', 'dosen.dosen'])
                    ->orderBy('hari')
                    ->orderBy('jam_mulai');
            },
        ])
            ->whereNull('deleted_at')
            ->find($kelasId);

        if (! $kelas) {
            abort(404, 'Kelas tidak ditemukan');
        }

        if ($idSemester !== null && (int) $kelas->id_semester !== $idSemester) {
            abort(422, 'Kelas tidak termasuk semester yang dipilih');
        }

        $jadwalIds = $kelas->jadwal->pluck('id')->filter()->values()->all();
        $perkuliahanRows = $jadwalIds === []
            ? collect()
            : Perkuliahan::whereIn('id_jadwal', $jadwalIds)->whereNull('deleted_at')->get();

        $jumlahMahasiswa = Krs::where('id_kelas', $kelasId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->count();

        $hadirByPerkuliahanId = $perkuliahanRows->isEmpty()
            ? collect()
            : Kehadiran::query()
                ->whereIn('id_perkuliahan', $perkuliahanRows->pluck('id'))
                ->where('status', 'hadir')
                ->whereNull('deleted_at')
                ->select('id_perkuliahan', DB::raw('count(*) as cnt'))
                ->groupBy('id_perkuliahan')
                ->pluck('cnt', 'id_perkuliahan');

        $hariOrder = [
            'senin' => 1,
            'selasa' => 2,
            'rabu' => 3,
            'kamis' => 4,
            'jumat' => 5,
            'sabtu' => 6,
            'minggu' => 7,
        ];

        $jadwalSorted = $kelas->jadwal->sortBy(function (Jadwal $j) use ($hariOrder) {
            $jamKey = (int) preg_replace('/\D/', '', substr((string) ($j->jam_mulai ?? '00:00'), 0, 5));
            $h = $hariOrder[strtolower((string) ($j->hari ?? ''))] ?? 8;
            if ($j->tanggal) {
                return sprintf('%s-%06d-%d', $j->tanggal->format('Y-m-d'), $jamKey, $j->id);
            }
            $u = (int) ($j->urutan_pertemuan ?? 9999);

            return sprintf('9999-99-99-%05d-%d-%06d-%d', $u, $h, $jamKey, $j->id);
        })->values();

        $km = $kelas->kurikulumMatkul;
        $m = $km?->matkul;
        $namaMatkulDisplay = trim(($km?->nama_matkul ?: $m?->nama) ?? '-');
        $kodeMatkulDisplay = ($km?->kode_matkul ?: $m?->kode) ?? '';
        $mataKuliahJudul = $kodeMatkulDisplay !== '' ? $kodeMatkulDisplay.' — '.$namaMatkulDisplay : $namaMatkulDisplay;
        $sksVal = (float) ($km?->sks ?? $m?->sks ?? 0);
        $sksStr = number_format($sksVal, 2, '.', '').' SKS';
        $namaDosenPic = $kelas->dosenPic?->nama ?? '-';
        $kodeKelas = $kelas->kode ?? '-';
        $prodiNama = strtoupper((string) ($kelas->prodi?->nama ?? ''));
        $sem = $kelas->semester;
        $semesterLine = $sem ? trim((string) (($sem->nama ?? '').' '.($sem->kode ?? ''))) : '';
        $subtitle = trim($prodiNama.($semesterLine !== '' ? ' '.$semesterLine : ''));

        Carbon::setLocale('id');

        $formatJamJadwal = static function (?string $jam): string {
            if ($jam === null || $jam === '') {
                return '-';
            }

            return substr($jam, 0, 5);
        };

        $rowsHtml = '';
        foreach ($jadwalSorted as $j) {
            $p = $this->findPerkuliahanForJadwalSlot($j, $perkuliahanRows);
            $statusArr = $this->sesiStatusForPerkuliahan($p);
            $sesiKey = $statusArr['sesi_status'];
            $statusLabel = match ($sesiKey) {
                'selesai' => 'Selesai',
                'sedang_berlangsung' => 'Dimulai',
                default => 'Terjadwal',
            };

            $tatapMuka = $j->urutan_pertemuan !== null ? (string) $j->urutan_pertemuan : '-';

            if ($j->tanggal) {
                try {
                    $hariTanggal = Carbon::parse($j->tanggal->format('Y-m-d'))->translatedFormat('l, d F Y');
                } catch (\Throwable) {
                    $hariTanggal = $j->tanggal->format('d/m/Y');
                }
            } else {
                $hariLabel = [
                    'senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis',
                    'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu',
                ][strtolower((string) ($j->hari ?? ''))] ?? ($j->hari ?? '-');
                $hariTanggal = $hariLabel.' (tanggal belum diisi)';
            }

            if ($p && $p->waktu_mulai) {
                $mulai = Carbon::parse($p->waktu_mulai)->format('H:i');
            } else {
                $mulai = $formatJamJadwal($j->jam_mulai);
            }

            if ($p && $p->waktu_selesai) {
                $selesai = Carbon::parse($p->waktu_selesai)->format('H:i');
            } elseif ($p && $p->waktu_mulai) {
                $selesai = $formatJamJadwal($j->jam_selesai);
            } else {
                $selesai = $formatJamJadwal($j->jam_selesai);
            }

            $ruang = $j->ruangan->nama ?? '-';
            $rencana = $j->bahasan ?? '';
            $realisasi = '';
            if ($p) {
                $realisasi = (string) ($p->realisasi_materi ?? '');
                if ($realisasi === '' && $p->waktu_selesai) {
                    $realisasi = (string) ($p->materi ?? '');
                }
            }

            $hadir = 0;
            if ($p) {
                $hadir = (int) ($hadirByPerkuliahanId[$p->id] ?? 0);
            }
            $kehadiranStr = '('.$hadir.'/'.$jumlahMahasiswa.')';

            $pengajar = $j->dosen->map(fn ($jd) => $jd->dosen->nama ?? null)->filter()->unique()->implode(', ');
            if ($pengajar === '') {
                $pengajar = '-';
            }

            $rowsHtml .= '<tr>'
                .'<td class="c">'.e($tatapMuka).'</td>'
                .'<td class="l">'.e($hariTanggal).'</td>'
                .'<td class="c">'.e($mulai).'</td>'
                .'<td class="c">'.e($selesai).'</td>'
                .'<td class="c">'.e($ruang).'</td>'
                .'<td class="c">'.e($statusLabel).'</td>'
                .'<td class="l small">'.nl2br(e($rencana)).'</td>'
                .'<td class="l small">'.nl2br(e($realisasi)).'</td>'
                .'<td class="c">'.e($kehadiranStr).'</td>'
                .'<td class="l small">'.e($pengajar).'</td>'
                .'<td class="ttd"></td>'
                .'</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="11" class="c">Belum ada jadwal pertemuan.</td></tr>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
.title { text-align: center; font-size: 13pt; font-weight: bold; margin: 0 0 6px 0; }
.subtitle { text-align: center; font-size: 10pt; font-weight: bold; margin: 0 0 14px 0; }
.meta { margin-bottom: 12px; line-height: 1.5; }
.meta-row { margin: 2px 0; }
.meta b { display: inline-block; min-width: 120px; }
table.jurnal { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.jurnal th, table.jurnal td { border: 1px solid #000; padding: 4px 3px; vertical-align: top; }
table.jurnal th { font-size: 7pt; font-weight: bold; text-align: center; background: #f0f0f0; }
td.c { text-align: center; }
td.l { text-align: left; }
td.small { font-size: 7pt; word-wrap: break-word; }
td.ttd { min-height: 28px; height: 32px; }
</style></head><body>';

        $html .= '<div class="title">JURNAL PERKULIAHAN</div>';
        $html .= '<div class="subtitle">'.e($subtitle !== '' ? $subtitle : '—').'</div>';
        $html .= '<div class="meta">';
        $html .= '<div class="meta-row"><b>MATA KULIAH:</b> '.e($mataKuliahJudul).'</div>';
        $html .= '<div class="meta-row"><b>NAMA DOSEN:</b> '.e($namaDosenPic).'</div>';
        $html .= '<div class="meta-row"><b>KREDIT/SKS:</b> '.e($sksStr).'</div>';
        $html .= '<div class="meta-row"><b>KELAS:</b> '.e($kodeKelas).'</div>';
        $html .= '</div>';

        $html .= '<table class="jurnal"><thead><tr>';
        $html .= '<th style="width:4%">TATAP MUKA KE</th>';
        $html .= '<th style="width:11%">HARI/TANGGAL</th>';
        $html .= '<th style="width:4%">MULAI</th>';
        $html .= '<th style="width:4%">SELESAI</th>';
        $html .= '<th style="width:5%">RUANG</th>';
        $html .= '<th style="width:6%">STATUS</th>';
        $html .= '<th style="width:14%">RENCANA MATERI</th>';
        $html .= '<th style="width:14%">REALISASI MATERI</th>';
        $html .= '<th style="width:7%">KEHADIRAN MHS</th>';
        $html .= '<th style="width:12%">PENGAJAR</th>';
        $html .= '<th style="width:9%">TANDA TANGAN</th>';
        $html .= '</tr></thead><tbody>';
        $html .= $rowsHtml;
        $html .= '</tbody></table></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $safeKode = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $kodeKelas);
        $filename = 'Jurnal_Perkuliahan_'.$safeKode.'_'.date('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array{src: string, remote: bool} src untuk atribut img; remote = perlu isRemoteEnabled di Dompdf.
     */
    private function rpsPdfResolveLogoSrc(?string $logoValue): array
    {
        if ($logoValue === null || trim($logoValue) === '') {
            return ['src' => '', 'remote' => false];
        }

        $v = trim($logoValue);
        if (str_starts_with($v, 'data:image')) {
            return ['src' => $v, 'remote' => false];
        }

        $tryDataUri = function (string $fullPath): ?string {
            if ($fullPath === '' || ! is_file($fullPath) || ! is_readable($fullPath)) {
                return null;
            }
            $mime = @mime_content_type($fullPath) ?: 'image/png';
            if (! str_starts_with((string) $mime, 'image/')) {
                return null;
            }
            $raw = @file_get_contents($fullPath);
            if ($raw === false) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        };

        if (preg_match('~/storage/([^\s?#]+)~', $v, $m)) {
            $rel = $m[1];
            foreach ([
                public_path('storage/'.$rel),
                storage_path('app/public/'.$rel),
            ] as $full) {
                $d = $tryDataUri($full);
                if ($d !== null) {
                    return ['src' => $d, 'remote' => false];
                }
            }
        }

        $pathPart = parse_url($v, PHP_URL_PATH);
        if (is_string($pathPart) && str_starts_with($pathPart, '/storage/')) {
            $rel = ltrim(substr($pathPart, strlen('/storage/')), '/');
            foreach ([
                public_path('storage/'.$rel),
                storage_path('app/public/'.$rel),
            ] as $full) {
                $d = $tryDataUri($full);
                if ($d !== null) {
                    return ['src' => $d, 'remote' => false];
                }
            }
        }

        if (str_starts_with($v, '/') && is_file(public_path(ltrim($v, '/')))) {
            $d = $tryDataUri(public_path(ltrim($v, '/')));
            if ($d !== null) {
                return ['src' => $d, 'remote' => false];
            }
        }

        if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
            return ['src' => $v, 'remote' => true];
        }

        return ['src' => '', 'remote' => false];
    }

    /**
     * Ubah teks/HTML dari editor RPS menjadi teks polos untuk PDF.
     */
    private function rpsPdfPlainText(?string $htmlOrText): string
    {
        if ($htmlOrText === null || trim($htmlOrText) === '') {
            return '';
        }
        $s = (string) $htmlOrText;
        $s = str_replace(['<br>', '<br/>', '<br />'], "\n", $s);
        $s = preg_replace('/<\/p>\s*/i', "\n", $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $s));
    }

    /**
     * Sel tabel: teks polos + nl2br + escape.
     */
    private function rpsPdfCell(?string $htmlOrText): string
    {
        $plain = $this->rpsPdfPlainText($htmlOrText);

        return $plain === '' ? '—' : nl2br(e($plain), false);
    }

    /**
     * Pecah paragraf sangat panjang di spasi terakhir sebelum batas, agar Dompdf tidak memotong konten.
     *
     * @return list<string>
     */
    private function rpsPdfSplitLongParagraph(string $text, int $maxLen = 2800): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $maxLen) {
            return $text === '' ? [] : [$text];
        }

        $parts = [];
        $remain = $text;
        while (mb_strlen($remain) > $maxLen) {
            $slice = mb_substr($remain, 0, $maxLen);
            $breakPos = mb_strrpos($slice, ' ');
            if ($breakPos === false || $breakPos < (int) ($maxLen / 3)) {
                $breakPos = $maxLen;
            }
            $parts[] = trim(mb_substr($remain, 0, $breakPos));
            $remain = trim(mb_substr($remain, $breakPos));
        }
        if ($remain !== '') {
            $parts[] = $remain;
        }

        return $parts;
    }

    /**
     * HTML beberapa &lt;p&gt; dari teks prosa agar Dompdf bisa memutus halaman (hindari satu blok panjang).
     */
    private function rpsPdfHtmlProsaFromPlain(?string $htmlOrText): string
    {
        $plain = $this->rpsPdfPlainText($htmlOrText);
        if ($plain === '') {
            return '<p class="p">—</p>';
        }

        $chunks = preg_split('/\n+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        if ($chunks === false) {
            $chunks = [$plain];
        }

        $out = '';
        foreach ($chunks as $chunk) {
            $t = trim((string) $chunk);
            if ($t === '') {
                continue;
            }
            foreach ($this->rpsPdfSplitLongParagraph($t) as $piece) {
                if ($piece === '') {
                    continue;
                }
                $out .= '<p class="p">'.nl2br(e($piece), false).'</p>';
            }
        }

        return $out !== '' ? $out : '<p class="p">—</p>';
    }

    /**
     * PDF RPS per kelas (format mengikuti dokumen RPS standar; kolom disesuaikan dengan skema aplikasi).
     */
    public function downloadRpsPdf(Request $request, int $kelasId): StreamedResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            abort(404, 'Data dosen tidak ditemukan');
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            abort(403, 'Anda tidak berhak mengakses RPS kelas ini');
        }

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul.jenisMatkul',
            'kurikulumMatkul.matkul.matkulPrasyaratLinks.matkulPrasyarat',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
            'dosenPic',
            'rps.rpsCpl',
            'rps.rpsCpmk.rpsSubcpmk',
            'rps.rpsPembelajaran',
        ])
            ->whereNull('deleted_at')
            ->find($kelasId);

        if (! $kelas) {
            abort(404, 'Kelas tidak ditemukan');
        }

        $rps = $kelas->rps;

        $univSettings = Setting::query()
            ->whereIn('key', ['app_univ_name', 'app_univ_logo'])
            ->pluck('value', 'key');

        $namaUnivSetting = trim((string) ($univSettings->get('app_univ_name') ?? ''));
        $institusi = $namaUnivSetting !== ''
            ? mb_strtoupper($namaUnivSetting, 'UTF-8')
            : mb_strtoupper((string) config('app.name', 'SIAK'), 'UTF-8');

        $logoForPdf = $this->rpsPdfResolveLogoSrc($univSettings->get('app_univ_logo'));

        $km = $kelas->kurikulumMatkul;
        $m = $km?->matkul;
        $namaMatkul = trim((string) (($km?->nama_matkul ?: $m?->nama) ?? '—'));
        $kodeMatkul = trim((string) (($km?->kode_matkul ?: $m?->kode) ?? ''));
        if ($kodeMatkul === '') {
            $kodeMatkul = '—';
        }
        $sksVal = (float) ($km?->sks ?? $m?->sks ?? 0);
        $sksStr = $sksVal > 0 ? (string) $sksVal : '—';
        $workloadMenit = $sksVal > 0 ? (string) (int) round($sksVal * 45) : '—';

        $jenisMkNama = $m?->jenisMatkul?->nama;
        $kelompokMk = ($jenisMkNama !== null && trim((string) $jenisMkNama) !== '')
            ? trim((string) $jenisMkNama)
            : '—';

        $semesterMk = '—';
        if ($km && $km->semester_rekomendasi !== null) {
            $semesterMk = (string) $km->semester_rekomendasi;
        }

        $semAkademik = $kelas->semester;
        $semAkademikStr = $semAkademik
            ? trim((string) (($semAkademik->nama ?? '').' ('.($semAkademik->kode ?? '').')'))
            : '—';

        $kodeKelas = $kelas->kode !== null && trim((string) $kelas->kode) !== '' ? (string) $kelas->kode : '—';
        $kelompokKelasNama = $kelas->kelompokKelas?->nama;
        $kelompokKelasStr = $kelompokKelasNama !== null && trim((string) $kelompokKelasNama) !== ''
            ? trim((string) $kelompokKelasNama)
            : '—';

        $prodiNama = $kelas->prodi?->nama ?? '—';
        $jenjangNama = $kelas->prodi?->jenjang?->nama ?? '';

        $prasyaratTampil = '—';
        $idMatkulInduk = $m?->id ?? ($km?->id_matkul !== null ? (int) $km->id_matkul : null);
        if ($idMatkulInduk) {
            if ($m) {
                $m->loadMissing('matkulPrasyaratLinks.matkulPrasyarat');
                $linkRows = $m->matkulPrasyaratLinks;
            } else {
                $linkRows = MatkulPrasyarat::query()
                    ->where('id_matkul', $idMatkulInduk)
                    ->whereNull('deleted_at')
                    ->with('matkulPrasyarat')
                    ->orderBy('id')
                    ->get();
            }
            $parts = [];
            foreach ($linkRows as $link) {
                $pr = $link->matkulPrasyarat;
                if (! $pr) {
                    continue;
                }
                $kc = trim((string) ($pr->kode ?? ''));
                $nm = trim((string) ($pr->nama ?? ''));
                if ($kc !== '' && $nm !== '') {
                    $parts[] = $kc.' — '.$nm;
                } elseif ($nm !== '') {
                    $parts[] = $nm;
                } elseif ($kc !== '') {
                    $parts[] = $kc;
                }
            }
            if ($parts !== []) {
                $prasyaratTampil = implode(', ', $parts);
            }
        }

        Carbon::setLocale('id');
        $tanggalTerbitIdentitas = '—';
        if ($rps && $rps->tanggal_penyusunan) {
            try {
                $tanggalTerbitIdentitas = $rps->tanggal_penyusunan->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $tanggalTerbitIdentitas = $rps->tanggal_penyusunan->format('Y-m-d H:i:s');
            }
        } elseif ($rps && $rps->updated_at) {
            try {
                $tanggalTerbitIdentitas = $rps->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $tanggalTerbitIdentitas = $rps->updated_at->format('Y-m-d H:i:s');
            }
        }

        $dosenPengampu = $kelas->dosenPic?->nama ?? '—';
        $dibuatOleh = $rps && $rps->created_by ? (string) $rps->created_by : $dosenPengampu;
        $diperiksaOleh = $rps && $rps->verified_by ? (string) $rps->verified_by : '—';
        $disetujuiOleh = $rps && $rps->approved_by ? (string) $rps->approved_by : '—';

        $css = <<<'CSS'
@page { margin: 12mm 14mm; }
html, body { height: auto; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
h1 { text-align: center; font-size: 12pt; margin: 0 0 4px 0; page-break-after: avoid; }
h2 { font-size: 10pt; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; page-break-after: avoid; }
.sub { text-align: center; font-size: 9pt; margin: 0 0 12px 0; font-weight: bold; }
table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
table.grid th, table.grid td { border: 1px solid #222; padding: 4px 5px; vertical-align: top; }
table.grid th { background: #e8e8e8; font-size: 7.5pt; font-weight: bold; text-align: center; }
td.lbl { font-weight: bold; width: 22%; background: #f5f5f5; font-size: 8pt; }
.sig td { height: 36px; font-size: 8pt; }
.p {
  text-align: justify;
  margin: 0 0 6px 0;
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
  page-break-inside: auto;
  orphans: 2;
  widows: 2;
}
div.p { page-break-inside: auto; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
ul.cpl { margin: 4px 0 8px 18px; padding: 0; }
ul.cpl li {
  margin-bottom: 4px;
  page-break-inside: auto;
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
}
table.plan { width: 100%; border-collapse: collapse; font-size: 6.5pt; table-layout: fixed; }
table.plan th, table.plan td { border: 1px solid #222; padding: 3px 2px; vertical-align: top; word-wrap: break-word; }
table.plan th { background: #e0e0e0; font-weight: bold; text-align: center; }
.break { page-break-after: always; }
.note { font-size: 8pt; color: #444; font-style: italic; }
table.rps-kop { width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #222; page-break-inside: avoid; }
table.rps-kop td { border: 1px solid #222; vertical-align: middle; padding: 8px 6px; }
td.rps-kop-logo { width: 24%; text-align: center; }
.rps-kop-img { max-height: 78px; max-width: 100%; display: block; margin: 0 auto; object-fit: contain; }
td.rps-kop-title { width: 76%; text-align: center; padding: 10px 12px; }
.rps-kop-univ { font-size: 13pt; font-weight: bold; text-transform: uppercase; line-height: 1.25; }
.rps-kop-rule { border-bottom: 1px solid #222; margin: 8px auto 8px auto; width: 90%; }
.rps-kop-doc { font-size: 11pt; font-weight: bold; text-transform: uppercase; }
.rps-section-title { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 8px 0 5px 0; page-break-after: avoid; }
table.rps-identitas { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; page-break-inside: avoid; }
table.rps-identitas th, table.rps-identitas td { border: 1px solid #222; padding: 5px 3px; text-align: center; vertical-align: middle; font-size: 7pt; word-wrap: break-word; }
table.rps-identitas th { background: #e8e8e8; font-weight: bold; }
.rps-kelas-meta { font-size: 7.5pt; color: #333; margin: 0 0 10px 0; text-align: center; line-height: 1.35; }
table.rps-sig { width: 100%; border-collapse: collapse; margin-bottom: 10px; page-break-inside: avoid; }
table.rps-sig th, table.rps-sig td { border: 1px solid #222; }
table.rps-sig th { background: #e8e8e8; font-size: 8pt; font-weight: bold; text-align: center; padding: 5px 4px; vertical-align: middle; }
table.rps-sig td.rps-sig-cell { height: 46mm; vertical-align: bottom; text-align: center; font-size: 8.5pt; padding: 6px 5px 8px 5px; line-height: 1.35; }
CSS;

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$css.'</style></head><body>';

        $html .= '<table class="rps-kop"><tr>';
        $html .= '<td class="rps-kop-logo">';
        if ($logoForPdf['src'] !== '') {
            $html .= '<img src="'.e($logoForPdf['src']).'" alt="" class="rps-kop-img" />';
        } else {
            $html .= ' ';
        }
        $html .= '</td>';
        $html .= '<td class="rps-kop-title">';
        $html .= '<div class="rps-kop-univ">'.e($institusi).'</div>';
        $html .= '<div class="rps-kop-rule"></div>';
        $html .= '<div class="rps-kop-doc">RENCANA PEMBELAJARAN SEMESTER (RPS)</div>';
        $html .= '</td></tr></table>';

        $html .= '<div class="rps-section-title">IDENTITAS MATA KULIAH</div>';
        $html .= '<table class="grid rps-identitas"><thead><tr>';
        $html .= '<th>Nama<br/>Mata Kuliah</th>';
        $html .= '<th>Kode<br/>Mata Kuliah</th>';
        $html .= '<th>Sks</th>';
        $html .= '<th>Workload</th>';
        $html .= '<th>Kelompok<br/>Matakuliah</th>';
        $html .= '<th>Semester</th>';
        $html .= '<th>Matakuliah<br/>Pra-Syarat</th>';
        $html .= '<th>Tanggal Terbit</th>';
        $html .= '</tr></thead><tbody><tr>';
        $html .= '<td>'.e($namaMatkul).'</td>';
        $html .= '<td>'.e($kodeMatkul).'</td>';
        $html .= '<td>'.e($sksStr).'</td>';
        $html .= '<td>'.e($workloadMenit).'</td>';
        $html .= '<td>'.e($kelompokMk).'</td>';
        $html .= '<td>'.e($semesterMk).'</td>';
        $html .= '<td>'.e($prasyaratTampil).'</td>';
        $html .= '<td>'.e($tanggalTerbitIdentitas).'</td>';
        $html .= '</tr></tbody></table>';

        $sigNamaKolom1 = $dibuatOleh;
        if ($dosenPengampu !== '—' && trim($dosenPengampu) !== trim($dibuatOleh)) {
            $sigNamaKolom1 .= "\n".$dosenPengampu;
        }
        $html .= '<table class="grid rps-sig"><thead><tr>';
        $html .= '<th>Dibuat Oleh<br/>Dosen Pengampu</th>';
        $html .= '<th>Diperiksa Oleh<br/>TPK Program Studi</th>';
        $html .= '<th>Disetujui Oleh<br/>Ketua Program Studi</th>';
        $html .= '</tr></thead><tbody><tr>';
        $html .= '<td class="rps-sig-cell">'.nl2br(e($sigNamaKolom1), false).'</td>';
        $html .= '<td class="rps-sig-cell">'.e($diperiksaOleh).'</td>';
        $html .= '<td class="rps-sig-cell">'.e($disetujuiOleh).'</td>';
        $html .= '</tr></tbody></table>';

        $html .= '<div class="break"></div>';

        if (! $rps) {
            $html .= '<p class="note">Belum ada data RPS untuk kelas ini. Simpan bagian deskripsi RPS di aplikasi terlebih dahulu.</p></body></html>';
        } else {
            $html .= '<h2>Deskripsi mata kuliah dan CPL</h2>';
            $html .= $this->rpsPdfHtmlProsaFromPlain($rps->deskripsi_matkul);

            $html .= '<h2>CPL yang dibebankan pada mata kuliah</h2>';
            $cplRows = $rps->rpsCpl;
            if ($cplRows->isEmpty()) {
                $html .= '<p class="p">—</p>';
            } else {
                $html .= '<ol class="cpl">';
                foreach ($cplRows as $idx => $c) {
                    $t = $this->rpsPdfPlainText($c->cpl);
                    $html .= '<li><strong>'.($idx + 1).'</strong> '.($t !== '' ? nl2br(e($t), false) : '—').'</li>';
                }
                $html .= '</ol>';
            }

            $html .= '<h2>Capaian pembelajaran mata kuliah (CPMK)</h2>';
            $cpmkRows = $rps->rpsCpmk;
            if ($cpmkRows->isEmpty()) {
                $html .= '<p class="p">—</p>';
            } else {
                $html .= '<ol class="cpl">';
                foreach ($cpmkRows as $idx => $c) {
                    $t = $this->rpsPdfPlainText($c->cpmk);
                    $html .= '<li><strong>CPMK-'.($idx + 1).'</strong> '.($t !== '' ? nl2br(e($t), false) : '—').'</li>';
                }
                $html .= '</ol>';
            }

            $html .= '<h2>Sub-CPMK</h2>';
            if ($cpmkRows->isEmpty()) {
                $html .= '<p class="p">—</p>';
            } else {
                $html .= '<table class="grid"><thead><tr><th style="width:8%">No.</th><th style="width:14%">CPMK</th><th>Sub-CPMK</th></tr></thead><tbody>';
                $no = 0;
                foreach ($cpmkRows as $ci => $c) {
                    $cpmkLabel = 'CPMK-'.($ci + 1);
                    $subs = $c->rpsSubcpmk;
                    if ($subs->isEmpty()) {
                        $no++;
                        $html .= '<tr><td style="text-align:center">'.$no.'</td><td>'.e($cpmkLabel).'</td><td>—</td></tr>';
                    } else {
                        foreach ($subs as $s) {
                            $no++;
                            $st = $this->rpsPdfPlainText($s->subcpmk);
                            $html .= '<tr><td style="text-align:center">'.$no.'</td><td>'.e($cpmkLabel).'</td><td>'
                                .($st !== '' ? nl2br(e($st), false) : '—').'</td></tr>';
                        }
                    }
                }
                $html .= '</tbody></table>';
            }

            $html .= '<h2>Materi perkuliahan (ringkas)</h2>';
            $html .= '<div class="p">'.$this->rpsPdfCell($rps->materi_kuliah).'</div>';

            $html .= '<h2>Strategi dan langkah pembelajaran</h2>';
            $html .= '<div class="p">'.$this->rpsPdfCell($rps->model_pembelajaran).'</div>';

            $html .= '<h2>Media pembelajaran</h2>';
            $html .= '<p class="p"><strong>Perangkat lunak:</strong><br>'.$this->rpsPdfCell($rps->media_perangkat_lunak).'</p>';
            $html .= '<p class="p"><strong>Perangkat keras:</strong><br>'.$this->rpsPdfCell($rps->media_perangkat_keras).'</p>';

            $html .= '<h2>Referensi</h2>';
            $html .= '<p class="p"><strong>Utama:</strong><br>'.$this->rpsPdfCell($rps->pustaka_utama).'</p>';
            $html .= '<p class="p"><strong>Pendukung:</strong><br>'.$this->rpsPdfCell($rps->pustaka_pendukung).'</p>';

            $html .= '<div class="break"></div>';
            $html .= '<h2>Rencana pembelajaran</h2>';
            $html .= '<p class="note">Kolom disesuaikan dengan data RPS di sistem: indikator capaian, materi, bentuk asesmen, pembelajaran sinkron/asinkron, dan bobot penilaian.</p>';
            $html .= '<table class="plan"><thead><tr>';
            $html .= '<th style="width:4%">Minggu ke</th>';
            $html .= '<th style="width:14%">Sub-CPMK</th>';
            $html .= '<th style="width:16%">Indikator capaian / penilaian</th>';
            $html .= '<th style="width:16%">Materi perkuliahan</th>';
            $html .= '<th style="width:12%">Bentuk &amp; kriteria penilaian</th>';
            $html .= '<th style="width:12%">Pembelajaran sinkron</th>';
            $html .= '<th style="width:12%">Pembelajaran asinkron</th>';
            $html .= '<th style="width:6%">Bobot (%)</th>';
            $html .= '</tr></thead><tbody>';

            $pbl = $rps->rpsPembelajaran;
            if ($pbl->isEmpty()) {
                $html .= '<tr><td colspan="8" style="text-align:center">Belum ada baris rencana pembelajaran.</td></tr>';
            } else {
                foreach ($pbl as $row) {
                    $minggu = $row->urutan_pertemuan !== null ? (string) $row->urutan_pertemuan : '—';
                    $bobot = $row->bobot !== null ? (string) $row->bobot : '—';
                    $html .= '<tr>';
                    $html .= '<td style="text-align:center">'.e($minggu).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->sub_cpmk).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->indikator_penilaian).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->materi ?? $row->materi_en).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->bentuk_kriteria_penilaian).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->pembelajaran_sinkron).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->pembelajaran_asinkron).'</td>';
                    $html .= '<td style="text-align:center">'.e($bobot).'</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table>';
            $html .= '</body></html>';
        }

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', $logoForPdf['remote']);
        $options->set('chroot', realpath(base_path()) ?: base_path());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $kodeMkFile = trim((string) (($km?->kode_matkul ?: $m?->kode) ?? ''));
        if ($kodeMkFile === '') {
            $kodeMkFile = 'NA';
        }
        $kodeKelasFile = ($kelas->kode !== null && trim((string) $kelas->kode) !== '')
            ? trim((string) $kelas->kode)
            : 'NA';
        $safeKode = preg_replace('/[^A-Za-z0-9._-]+/', '_', $kodeMkFile.'_'.$kodeKelasFile);
        $safeKode = trim((string) $safeKode, '._-') ?: 'NA_NA';
        $filename = 'RPS_'.$safeKode.'_'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
