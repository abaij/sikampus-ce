<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\JadwalDosen;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KehadiranController extends Controller
{
    /**
     * Get kehadiran by perkuliahan ID
     */
    public function getByPerkuliahan(Request $request, int $perkuliahanId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $perkuliahan = Perkuliahan::with([
            'jadwal.kelas.kurikulumMatkul.matkul',
            'jadwal.kelas.kurikulumMatkul.kurikulum',
            'jadwal.kelas.prodi',
            'jadwal.kelas.prodi.jenjang',
            'jadwal.kelas.semester',
        ])->find($perkuliahanId);

        if (! $perkuliahan || ! $perkuliahan->jadwal || ! $perkuliahan->jadwal->kelas) {
            return response()->json([
                'message' => 'Perkuliahan tidak ditemukan',
            ], 404);
        }

        if (! $this->dosenBisaAksesPerkuliahan($dosen, $perkuliahan)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke perkuliahan ini',
            ], 403);
        }

        $kelas = $perkuliahan->jadwal->kelas;

        // Ambil daftar mahasiswa dari KRS
        $mahasiswaList = Krs::with([
            'mahasiswa:id,nim,nama',
            'mahasiswa.prodi:id,nama',
        ])
            ->where('id_kelas', $kelas->id)
            ->where('approved_at', '!=', null)
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($krs) use ($perkuliahanId) {
                // Ambil kehadiran jika sudah ada
                $kehadiran = Kehadiran::where('id_perkuliahan', $perkuliahanId)
                    ->where('id_mhs', $krs->id_mahasiswa)
                    ->first();

                return [
                    'id_krs' => $krs->id,
                    'mahasiswa' => [
                        'id' => $krs->mahasiswa->id,
                        'nim' => $krs->mahasiswa->nim,
                        'nama' => $krs->mahasiswa->nama,
                        'prodi' => $krs->mahasiswa->prodi ? [
                            'id' => $krs->mahasiswa->prodi->id,
                            'nama' => $krs->mahasiswa->prodi->nama,
                        ] : null,
                    ],
                    'kehadiran' => $kehadiran ? [
                        'id' => $kehadiran->id,
                        'status' => $kehadiran->status,
                        'keterangan' => $kehadiran->keterangan,
                    ] : null,
                ];
            });

        return response()->json([
            'perkuliahan' => $perkuliahan,
            'mahasiswa' => $mahasiswaList,
        ]);
    }

    /**
     * Store or update kehadiran (bulk)
     */
    public function storeOrUpdate(Request $request, int $perkuliahanId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $perkuliahan = Perkuliahan::with('jadwal.kelas')->find($perkuliahanId);

        if (! $perkuliahan || ! $perkuliahan->jadwal || ! $perkuliahan->jadwal->kelas) {
            return response()->json([
                'message' => 'Perkuliahan tidak ditemukan',
            ], 404);
        }

        if (! $this->dosenBisaAksesPerkuliahan($dosen, $perkuliahan)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke perkuliahan ini',
            ], 403);
        }

        $validated = $request->validate([
            'kehadiran' => ['required', 'array'],
            'kehadiran.*.id_mhs' => ['required', 'integer', 'exists:mahasiswa,id'],
            'kehadiran.*.status' => ['required', 'string', 'in:hadir,izin,alfa,sakit'],
            'kehadiran.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['kehadiran'] as $item) {
                Kehadiran::updateOrCreate(
                    [
                        'id_perkuliahan' => $perkuliahanId,
                        'id_mhs' => $item['id_mhs'],
                    ],
                    [
                        'status' => $item['status'],
                        'keterangan' => $item['keterangan'] ?? null,
                        'created_by' => $user->name ?? $user->email ?? null,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Kehadiran berhasil disimpan',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan kehadiran: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete kehadiran
     */
    public function destroy(int $id): JsonResponse
    {
        $user = request()->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $kehadiran = Kehadiran::find($id);

        if (! $kehadiran) {
            return response()->json([
                'message' => 'Kehadiran tidak ditemukan',
            ], 404);
        }

        $perkuliahan = Perkuliahan::with('jadwal.kelas')->find($kehadiran->id_perkuliahan);

        if (! $perkuliahan || ! $perkuliahan->jadwal || ! $perkuliahan->jadwal->kelas) {
            return response()->json([
                'message' => 'Perkuliahan tidak ditemukan',
            ], 404);
        }

        if (! $this->dosenBisaAksesPerkuliahan($dosen, $perkuliahan)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kehadiran ini',
            ], 403);
        }

        $kehadiran->delete();

        return response()->json([
            'message' => 'Kehadiran berhasil dihapus',
        ]);
    }

    /**
     * Get rekap kehadiran by kelas ID
     */
    public function getRekapByKelas(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        // Ambil data kelas dengan relasi lengkap
        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->find($kelasId);

        if (! $kelas) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan',
            ], 404);
        }

        if (! $this->dosenBisaAksesKelas($dosen, $kelas)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kelas ini',
            ], 403);
        }

        // Ambil semua jadwal untuk kelas ini
        $jadwalIds = \App\Models\Jadwal::where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        [$perkuliahanList, $perkuliahanIdToCol] = $this->perkuliahanSortedForRekap($jadwalIds);

        // Ambil semua mahasiswa dari KRS untuk kelas ini
        $mahasiswaList = Krs::with([
            'mahasiswa:id,nim,nama',
        ])
            ->where('krs.id_kelas', $kelasId)
            ->whereNull('krs.deleted_at')
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->whereNull('mahasiswa.deleted_at')
            ->orderBy('mahasiswa.nim')
            ->select('krs.*')
            ->get();

        // Ambil semua kehadiran untuk perkuliahan di kelas ini
        $perkuliahanIds = $perkuliahanList->pluck('id')->toArray();
        $kehadiranList = Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
            ->get()
            ->groupBy('id_perkuliahan')
            ->map(function ($items) {
                return $items->keyBy('id_mhs');
            });

        // Format data mahasiswa dengan kehadiran per pertemuan
        $mahasiswaData = $mahasiswaList->map(function ($krs) use ($perkuliahanList, $kehadiranList, $perkuliahanIdToCol) {
            $kehadiranPerPertemuan = [];

            foreach ($perkuliahanList as $perkuliahan) {
                $col = $perkuliahanIdToCol[(int) $perkuliahan->id] ?? null;
                if ($col === null) {
                    continue;
                }
                $kehadiran = $kehadiranList[$perkuliahan->id][$krs->id_mahasiswa] ?? null;
                $kehadiranPerPertemuan[$col] = $kehadiran ? [
                    'status' => $kehadiran->status,
                    'keterangan' => $kehadiran->keterangan,
                ] : null;
            }

            return [
                'id_mahasiswa' => $krs->mahasiswa->id,
                'nim' => $krs->mahasiswa->nim,
                'nama' => $krs->mahasiswa->nama,
                'kehadiran' => $kehadiranPerPertemuan,
            ];
        });

        // Format data dosen
        $dosenData = [
            'nama' => $dosen->nama,
            'gelar_depan' => $dosen->gelar_depan,
            'gelar_belakang' => $dosen->gelar_belakang,
            'nidn' => $dosen->nidn,
        ];

        // Format nama dosen lengkap
        $namaDosenLengkap = trim(
            ($dosenData['gelar_depan'] ? $dosenData['gelar_depan'].' ' : '').
            $dosenData['nama'].
            ($dosenData['gelar_belakang'] ? ', '.$dosenData['gelar_belakang'] : '')
        );

        return response()->json([
            'dosen' => [
                'nama' => $namaDosenLengkap,
                'nidn' => $dosenData['nidn'],
            ],
            'kelas' => [
                'id' => $kelas->id,
                'kode_matkul' => (! empty($kelas->kurikulumMatkul->kode_matkul))
                    ? $kelas->kurikulumMatkul->kode_matkul
                    : ($kelas->kurikulumMatkul->matkul->kode ?? null),
                'nama_matkul' => (! empty($kelas->kurikulumMatkul->nama_matkul))
                    ? $kelas->kurikulumMatkul->nama_matkul
                    : ($kelas->kurikulumMatkul->matkul->nama ?? null),
                'matkul' => $kelas->kurikulumMatkul->matkul ? [
                    'kode' => $kelas->kurikulumMatkul->matkul->kode,
                    'nama' => $kelas->kurikulumMatkul->matkul->nama,
                ] : null,
                'prodi' => $kelas->prodi ? [
                    'nama' => $kelas->prodi->nama,
                    'jenjang' => $kelas->prodi->jenjang ? $kelas->prodi->jenjang->nama : null,
                ] : null,
                'semester' => $kelas->semester ? [
                    'kode' => $kelas->semester->kode,
                    'nama' => $kelas->semester->nama,
                ] : null,
            ],
            'perkuliahan' => $perkuliahanList->map(function ($p) use ($perkuliahanIdToCol) {
                return [
                    'id' => $p->id,
                    'pertemuan_ke' => $perkuliahanIdToCol[(int) $p->id] ?? null,
                    'tanggal' => $p->waktu_mulai?->format('Y-m-d'),
                    'materi' => $p->materi,
                ];
            }),
            'mahasiswa' => $mahasiswaData,
        ]);
    }

    /**
     * Rekap kehadiran mahasiswa yang login untuk satu kelas (semua pertemuan).
     */
    public function getRekapByKelasForMahasiswa(Request $request, int $kelasId): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();
        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $hasKrs = Krs::where('id_mahasiswa', $mahasiswa->id)
            ->where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasKrs) {
            return response()->json([
                'message' => 'Anda tidak terdaftar di kelas ini',
            ], 403);
        }

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->find($kelasId);

        if (! $kelas) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan',
            ], 404);
        }

        $jadwalIds = \App\Models\Jadwal::where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        [$perkuliahanList, $perkuliahanIdToCol] = $this->perkuliahanSortedForRekap($jadwalIds);

        $perkuliahanIds = $perkuliahanList->pluck('id')->toArray();
        $kehadiranByPerkuliahanId = collect();
        if ($perkuliahanIds !== []) {
            $kehadiranByPerkuliahanId = Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
                ->where('id_mhs', $mahasiswa->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id_perkuliahan');
        }

        $pertemuan = $perkuliahanList->map(function (Perkuliahan $p) use ($perkuliahanIdToCol, $kehadiranByPerkuliahanId) {
            /** @var Kehadiran|null $kh */
            $kh = $kehadiranByPerkuliahanId->get($p->id);

            return [
                'id' => $p->id,
                'pertemuan_ke' => $perkuliahanIdToCol[(int) $p->id] ?? null,
                'tanggal' => $p->waktu_mulai?->format('Y-m-d'),
                'waktu_mulai' => $p->waktu_mulai ? $p->waktu_mulai->format('Y-m-d H:i:s') : null,
                'waktu_selesai' => $p->waktu_selesai ? $p->waktu_selesai->format('Y-m-d H:i:s') : null,
                'materi' => $p->materi,
                'realisasi_materi' => $p->realisasi_materi,
                'kehadiran' => $kh ? [
                    'status' => $kh->status,
                    'keterangan' => $kh->keterangan,
                ] : null,
            ];
        });

        $ringkasan = [
            'total_pertemuan' => $pertemuan->count(),
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'alfa' => 0,
            'belum_catat' => 0,
        ];

        foreach ($pertemuan as $row) {
            $st = $row['kehadiran']['status'] ?? null;
            if ($st === null || $st === '') {
                $ringkasan['belum_catat']++;
            } else {
                $key = strtolower((string) $st);
                if (isset($ringkasan[$key])) {
                    $ringkasan[$key]++;
                }
            }
        }

        return response()->json([
            'kelas' => [
                'id' => $kelas->id,
                'kode_matkul' => (! empty($kelas->kurikulumMatkul->kode_matkul))
                    ? $kelas->kurikulumMatkul->kode_matkul
                    : ($kelas->kurikulumMatkul->matkul->kode ?? null),
                'nama_matkul' => (! empty($kelas->kurikulumMatkul->nama_matkul))
                    ? $kelas->kurikulumMatkul->nama_matkul
                    : ($kelas->kurikulumMatkul->matkul->nama ?? null),
                'matkul' => $kelas->kurikulumMatkul->matkul ? [
                    'kode' => $kelas->kurikulumMatkul->matkul->kode,
                    'nama' => $kelas->kurikulumMatkul->matkul->nama,
                ] : null,
                'prodi' => $kelas->prodi ? [
                    'nama' => $kelas->prodi->nama,
                    'jenjang' => $kelas->prodi->jenjang ? $kelas->prodi->jenjang->nama : null,
                ] : null,
                'semester' => $kelas->semester ? [
                    'id' => $kelas->semester->id,
                    'kode' => $kelas->semester->kode,
                    'nama' => $kelas->semester->nama,
                ] : null,
                'dosen_pic' => $kelas->dosenPic ? [
                    'nama' => $kelas->dosenPic->nama,
                ] : null,
            ],
            'pertemuan' => $pertemuan->values()->all(),
            'ringkasan' => $ringkasan,
        ]);
    }

    /**
     * Get rekap kehadiran by kelas ID untuk admin
     */
    public function getRekapByKelasAdmin(Request $request, int $kelasId): JsonResponse
    {
        // Ambil data kelas dengan relasi lengkap
        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->find($kelasId);

        if (! $kelas) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan',
            ], 404);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data kehadiran kelas ini.');
            }
        }

        // Ambil semua jadwal untuk kelas ini
        $jadwalIds = \App\Models\Jadwal::where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        [$perkuliahanList, $perkuliahanIdToCol] = $this->perkuliahanSortedForRekap($jadwalIds);

        // Ambil semua mahasiswa dari KRS untuk kelas ini
        $mahasiswaList = Krs::with([
            'mahasiswa:id,nim,nama',
        ])
            ->where('krs.id_kelas', $kelasId)
            ->whereNull('krs.deleted_at')
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->whereNull('mahasiswa.deleted_at')
            ->orderBy('mahasiswa.nim')
            ->select('krs.*')
            ->get();

        // Ambil semua kehadiran untuk perkuliahan di kelas ini
        $perkuliahanIds = $perkuliahanList->pluck('id')->toArray();
        $kehadiranList = Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
            ->get()
            ->groupBy('id_perkuliahan')
            ->map(function ($items) {
                return $items->keyBy('id_mhs');
            });

        // Format data mahasiswa dengan kehadiran per pertemuan
        $mahasiswaData = $mahasiswaList->map(function ($krs) use ($perkuliahanList, $kehadiranList, $perkuliahanIdToCol) {
            $kehadiranPerPertemuan = [];

            foreach ($perkuliahanList as $perkuliahan) {
                $col = $perkuliahanIdToCol[(int) $perkuliahan->id] ?? null;
                if ($col === null) {
                    continue;
                }
                $kehadiran = $kehadiranList[$perkuliahan->id][$krs->id_mahasiswa] ?? null;
                $kehadiranPerPertemuan[$col] = $kehadiran ? [
                    'status' => $kehadiran->status,
                    'keterangan' => $kehadiran->keterangan,
                ] : null;
            }

            return [
                'id_mahasiswa' => $krs->mahasiswa->id,
                'nim' => $krs->mahasiswa->nim,
                'nama' => $krs->mahasiswa->nama,
                'kehadiran' => $kehadiranPerPertemuan,
            ];
        });

        return response()->json([
            'kelas' => [
                'id' => $kelas->id,
                'kode_matkul' => (! empty($kelas->kurikulumMatkul->kode_matkul))
                    ? $kelas->kurikulumMatkul->kode_matkul
                    : ($kelas->kurikulumMatkul->matkul->kode ?? null),
                'nama_matkul' => (! empty($kelas->kurikulumMatkul->nama_matkul))
                    ? $kelas->kurikulumMatkul->nama_matkul
                    : ($kelas->kurikulumMatkul->matkul->nama ?? null),
                'matkul' => $kelas->kurikulumMatkul->matkul ? [
                    'kode' => $kelas->kurikulumMatkul->matkul->kode,
                    'nama' => $kelas->kurikulumMatkul->matkul->nama,
                ] : null,
                'prodi' => $kelas->prodi ? [
                    'nama' => $kelas->prodi->nama,
                    'jenjang' => $kelas->prodi->jenjang ? $kelas->prodi->jenjang->nama : null,
                ] : null,
                'semester' => $kelas->semester ? [
                    'kode' => $kelas->semester->kode,
                    'nama' => $kelas->semester->nama,
                ] : null,
            ],
            'perkuliahan' => $perkuliahanList->map(function ($p) use ($perkuliahanIdToCol) {
                return [
                    'id' => $p->id,
                    'pertemuan_ke' => $perkuliahanIdToCol[(int) $p->id] ?? null,
                    'tanggal' => $p->waktu_mulai?->format('Y-m-d'),
                    'materi' => $p->materi,
                ];
            }),
            'mahasiswa' => $mahasiswaData,
        ]);
    }

    /**
     * Export rekap kehadiran ke Excel untuk admin
     */
    public function exportRekapByKelasAdmin(Request $request, int $kelasId): StreamedResponse
    {
        // Ambil data kelas dengan relasi lengkap
        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->find($kelasId);

        if (! $kelas) {
            throw new \Exception('Kelas tidak ditemukan');
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data kehadiran kelas ini.');
            }
        }

        // Ambil semua jadwal untuk kelas ini
        $jadwalIds = \App\Models\Jadwal::where('id_kelas', $kelasId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        [$perkuliahanList, $perkuliahanIdToCol] = $this->perkuliahanSortedForRekap($jadwalIds);

        // Ambil semua mahasiswa dari KRS untuk kelas ini
        $mahasiswaList = Krs::with([
            'mahasiswa:id,nim,nama',
        ])
            ->where('krs.id_kelas', $kelasId)
            ->whereNull('krs.deleted_at')
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->whereNull('mahasiswa.deleted_at')
            ->orderBy('mahasiswa.nim')
            ->select('krs.*')
            ->get();

        // Ambil semua kehadiran untuk perkuliahan di kelas ini
        $perkuliahanIds = $perkuliahanList->pluck('id')->toArray();
        $kehadiranList = Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
            ->get()
            ->groupBy('id_perkuliahan')
            ->map(function ($items) {
                return $items->keyBy('id_mhs');
            });

        // Format data mahasiswa dengan kehadiran per pertemuan
        $mahasiswaData = $mahasiswaList->map(function ($krs) use ($perkuliahanList, $kehadiranList, $perkuliahanIdToCol) {
            $kehadiranPerPertemuan = [];

            foreach ($perkuliahanList as $perkuliahan) {
                $col = $perkuliahanIdToCol[(int) $perkuliahan->id] ?? null;
                if ($col === null) {
                    continue;
                }
                $kehadiran = $kehadiranList[$perkuliahan->id][$krs->id_mahasiswa] ?? null;
                $kehadiranPerPertemuan[$col] = $kehadiran ? $kehadiran->status : null;
            }

            return [
                'nim' => $krs->mahasiswa->nim,
                'nama' => $krs->mahasiswa->nama,
                'kehadiran' => $kehadiranPerPertemuan,
            ];
        });

        // Buat spreadsheet
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Kehadiran');

        // Info kelas
        $kodeMatkul = (! empty($kelas->kurikulumMatkul->kode_matkul))
            ? $kelas->kurikulumMatkul->kode_matkul
            : ($kelas->kurikulumMatkul->matkul->kode ?? '-');
        $namaMatkul = (! empty($kelas->kurikulumMatkul->nama_matkul))
            ? $kelas->kurikulumMatkul->nama_matkul
            : ($kelas->kurikulumMatkul->matkul->nama ?? '-');
        $prodiNama = $kelas->prodi ? $kelas->prodi->nama : '-';
        $jenjangNama = $kelas->prodi && $kelas->prodi->jenjang ? $kelas->prodi->jenjang->nama : '';
        $semesterNama = $kelas->semester ? $kelas->semester->nama : '-';
        $semesterKode = $kelas->semester ? $kelas->semester->kode : '-';

        // Header info
        $row = 1;
        $sheet->setCellValue('A'.$row, 'REKAP KEHADIRAN MAHASISWA');
        $row++;
        $sheet->setCellValue('A'.$row, 'Mata Kuliah: '.$kodeMatkul.' - '.$namaMatkul);
        $row++;
        $sheet->setCellValue('A'.$row, 'Program Studi: '.$prodiNama.($jenjangNama ? ' ('.$jenjangNama.')' : ''));
        $row++;
        $sheet->setCellValue('A'.$row, 'Semester: '.$semesterNama.' ('.$semesterKode.')');
        $row++;
        $sheet->setCellValue('A'.$row, 'Tanggal Export: '.date('d/m/Y H:i:s'));
        $row += 2;

        // Header tabel
        $headers = ['No', 'NIM', 'Nama'];
        for ($i = 1; $i <= 16; $i++) {
            $headers[] = $i;
        }
        $sheet->fromArray([$headers], null, 'A'.$row);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $lastHeaderCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A'.$row.':'.$lastHeaderCol.$row)->applyFromArray($headerStyle);

        // Data rows
        $row++;
        $no = 1;
        foreach ($mahasiswaData as $mhs) {
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $mhs['nim']);
            $sheet->setCellValue('C'.$row, $mhs['nama']);

            $colIndex = 3; // Start from column D (index 3 = D)
            for ($pertemuan = 1; $pertemuan <= 16; $pertemuan++) {
                $status = $mhs['kehadiran'][$pertemuan] ?? null;
                $statusLabel = '';
                if ($status) {
                    switch (strtolower($status)) {
                        case 'hadir':
                            $statusLabel = 'H';
                            break;
                        case 'izin':
                            $statusLabel = 'I';
                            break;
                        case 'sakit':
                            $statusLabel = 'S';
                            break;
                        case 'alfa':
                            $statusLabel = 'A';
                            break;
                        default:
                            $statusLabel = $status;
                    }
                }
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($col.$row, $statusLabel);
                $colIndex++;
            }
            $row++;
            $no++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8); // No
        $sheet->getColumnDimension('B')->setWidth(15); // NIM
        $sheet->getColumnDimension('C')->setWidth(30); // Nama
        for ($i = 4; $i <= 19; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setWidth(6); // Pertemuan
        }

        // Center align untuk No dan Pertemuan
        $sheet->getStyle('A'.($row - count($mahasiswaData)).':A'.($row - 1))
            ->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        for ($i = 4; $i <= 19; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($col.($row - count($mahasiswaData)).':'.$col.($row - 1))
                ->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // Filename
        $filename = 'rekap_kehadiran_'.str_replace([' ', '/'], '_', $kodeMatkul).'_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Urut perkuliahan kronologis untuk rekap; indeks kolom pertemuan 1..n mengacu pada urutan ini.
     *
     * @return array{\Illuminate\Support\Collection<int, Perkuliahan>, array<int, int>}
     */
    private function perkuliahanSortedForRekap(array $jadwalIds): array
    {
        if ($jadwalIds === []) {
            return [collect(), []];
        }

        $list = Perkuliahan::whereIn('id_jadwal', $jadwalIds)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy([
                fn (Perkuliahan $p) => $p->waktu_mulai?->getTimestamp() ?? \PHP_INT_MAX,
                fn (Perkuliahan $p) => $p->id,
            ])
            ->values();

        $idToCol = [];
        foreach ($list as $i => $p) {
            $idToCol[(int) $p->id] = $i + 1;
        }

        return [$list, $idToCol];
    }

    /**
     * Dosen PIC, pengampu di kelas_dosen, atau pengampu slot jadwal kelas boleh akses kehadiran kelas.
     */
    private function dosenBisaAksesKelas(Dosen $dosen, Kelas $kelas): bool
    {
        if ((int) $kelas->id_dosen_pic === (int) $dosen->id) {
            return true;
        }

        if (KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelas->id)
            ->whereNull('deleted_at')
            ->exists()) {
            return true;
        }

        return JadwalDosen::where('id_dosen', $dosen->id)
            ->where('status', 'active')
            ->whereHas('jadwal', function ($q) use ($kelas): void {
                $q->where('id_kelas', $kelas->id)->whereNull('deleted_at');
            })
            ->exists();
    }

    private function dosenBisaAksesPerkuliahan(Dosen $dosen, Perkuliahan $perkuliahan): bool
    {
        $kelas = $perkuliahan->jadwal->kelas;
        if ((int) $kelas->id_dosen_pic === (int) $dosen->id) {
            return true;
        }

        if (KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelas->id)
            ->whereNull('deleted_at')
            ->exists()) {
            return true;
        }

        return JadwalDosen::where('id_jadwal', $perkuliahan->id_jadwal)
            ->where('id_dosen', $dosen->id)
            ->where('status', 'active')
            ->exists();
    }
}
