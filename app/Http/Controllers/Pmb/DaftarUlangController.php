<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Mail\PmbDaftarUlangSelesaiMail;
use App\Models\Mahasiswa;
use App\Models\PmbCamaba;
use App\Models\PmbDaftarUlang;
use App\Models\Semester;
use App\Models\StatusAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class DaftarUlangController extends Controller
{
    /**
     * Daftar daftar ulang (paginasi + pencarian).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status');
        $idPeriodeParam = $request->get('id_periode');
        $idPeriode = null;
        if ($idPeriodeParam !== null && $idPeriodeParam !== '') {
            $idPeriode = (int) $idPeriodeParam;
            if ($idPeriode <= 0) {
                $idPeriode = null;
            }
        }

        $query = PmbDaftarUlang::query()
            ->with([
                'pendaftaran.camaba',
                'pendaftaran.periode',
                'prodi',
            ])
            ->orderByDesc('updated_at');

        if ($idPeriode) {
            $query->whereHas('pendaftaran', static function ($q) use ($idPeriode): void {
                $q->where('id_periode', $idPeriode);
            });
        }

        if ($status !== null && $status !== '') {
            if ($status === 'pending') {
                $query->whereHas('pendaftaran.camaba', static function ($q): void {
                    $q->whereNull('status_herregistrasi')
                        ->orWhere('status_herregistrasi', 'pending');
                });
            } elseif ($status === 'herregistrasi') {
                $query->whereHas('pendaftaran.camaba', static function ($cq): void {
                    $cq->where('status_herregistrasi', 'herregistrasi');
                });
            } elseif ($status === 'acc') {
                $query->where(function ($q): void {
                    $q->where('pmb_daftar_ulang.status', 'acc')
                        ->orWhereHas('pendaftaran.camaba', static function ($cq): void {
                            $cq->where('status_herregistrasi', 'acc');
                        });
                });
            } else {
                $query->where('pmb_daftar_ulang.status', $status);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('pendaftaran', function ($pq) use ($search) {
                    $pq->where('no_pendaftaran', 'like', "%{$search}%")
                        ->orWhereHas('camaba', function ($cq) use ($search) {
                            $cq->where('nama', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('nim', 'like', "%{$search}%");
                        });
                })->orWhereHas('prodi', function ($prq) use ($search) {
                    $prq->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode', 'like', "%{$search}%");
                });
            });
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_pendaftaran' => [
                'required',
                'integer',
                'exists:pmb_pendaftaran,id',
                Rule::unique('pmb_daftar_ulang', 'id_pendaftaran')->whereNull('deleted_at'),
            ],
            'id_prodi' => ['required', 'integer', 'exists:prodi,id'],
            'tanggal_daftar_ulang' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50', Rule::in(['pending', 'herregistrasi', 'acc'])],
        ]);

        $herreStatus = $validated['status'] ?? 'pending';
        unset($validated['status']);

        $row = PmbDaftarUlang::create($validated);
        $row->load(['pendaftaran.camaba', 'pendaftaran.periode', 'prodi']);

        $camaba = $row->pendaftaran?->camaba;
        if ($camaba instanceof PmbCamaba) {
            $camaba->update(['status_herregistrasi' => $herreStatus]);
            $row->load(['pendaftaran.camaba', 'pendaftaran.periode', 'prodi']);
        }

        return response()->json($row, 201);
    }

    public function show(PmbDaftarUlang $pmb_daftar_ulang): JsonResponse
    {
        $pmb_daftar_ulang->load([
            'pendaftaran.camaba.kotaLahir',
            'pendaftaran.camaba.kota',
            'pendaftaran.camaba.kecamatan',
            'pendaftaran.camaba.provinsi',
            'pendaftaran.camaba.negara',
            'pendaftaran.camaba.agama',
            'pendaftaran.periode',
            'pendaftaran.jalurMasuk',
            'pendaftaran.jenisDaftar',
            'prodi',
        ]);

        return response()->json($pmb_daftar_ulang);
    }

    public function update(Request $request, PmbDaftarUlang $pmb_daftar_ulang): JsonResponse
    {
        $pmb_daftar_ulang->loadMissing('pendaftaran.camaba');
        $camaba = $pmb_daftar_ulang->pendaftaran?->camaba;

        $rules = [
            'id_pendaftaran' => [
                'sometimes',
                'required',
                'integer',
                'exists:pmb_pendaftaran,id',
                Rule::unique('pmb_daftar_ulang', 'id_pendaftaran')
                    ->ignore($pmb_daftar_ulang->id)
                    ->whereNull('deleted_at'),
            ],
            'id_prodi' => ['sometimes', 'required', 'integer', 'exists:prodi,id'],
            'tanggal_daftar_ulang' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50', Rule::in(['pending', 'herregistrasi', 'acc'])],
        ];

        if ($camaba) {
            $rules['nim'] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('pmb_camaba', 'nim')->ignore($camaba->id)->whereNull('deleted_at'),
            ];
        }

        $validated = $request->validate($rules);

        $camabaUpdates = [];
        if ($camaba && array_key_exists('status', $validated)) {
            $camabaUpdates['status_herregistrasi'] = $validated['status'] ?? 'pending';
            unset($validated['status']);
        }
        if ($camaba && array_key_exists('nim', $validated)) {
            $nim = $validated['nim'];
            $camabaUpdates['nim'] = $nim === '' || $nim === null ? null : $nim;
            unset($validated['nim']);
        }
        if ($camaba !== null && $camabaUpdates !== []) {
            $camaba->update($camabaUpdates);
        }

        if ($validated !== []) {
            $pmb_daftar_ulang->update($validated);
        }

        return response()->json(
            $pmb_daftar_ulang->fresh()->load(['pendaftaran.camaba', 'pendaftaran.periode', 'prodi'])
        );
    }

    public function destroy(PmbDaftarUlang $pmb_daftar_ulang): JsonResponse
    {
        $pmb_daftar_ulang->delete();

        return response()->json(['message' => 'Data daftar ulang dihapus']);
    }

    /**
     * Kirim email ke camaba: daftar ulang selesai (prodi & NIM).
     */
    public function kirimEmailSelesai(PmbDaftarUlang $pmb_daftar_ulang): JsonResponse
    {
        $pmb_daftar_ulang->load(['pendaftaran.camaba', 'prodi']);

        if ($pmb_daftar_ulang->status !== 'acc') {
            return response()->json([
                'success' => false,
                'message' => 'Email hanya dapat dikirim jika status diterima pada data camaba sudah diset.',
            ], 422);
        }

        $camaba = $pmb_daftar_ulang->pendaftaran?->camaba;
        if (! $camaba || ! filter_var($camaba->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email camaba tidak valid atau kosong.',
            ], 422);
        }

        $prodi = $pmb_daftar_ulang->prodi;
        if (! $prodi) {
            return response()->json([
                'success' => false,
                'message' => 'Data program studi tidak ditemukan.',
            ], 422);
        }

        $noPendaftaran = $pmb_daftar_ulang->pendaftaran?->no_pendaftaran
            ?? 'PMB-'.$pmb_daftar_ulang->id_pendaftaran;

        Mail::to($camaba->email)->send(new PmbDaftarUlangSelesaiMail(
            namaCamaba: $camaba->nama,
            noPendaftaran: $noPendaftaran,
            namaProdi: $prodi->nama,
            kodeProdi: $prodi->kode,
            nim: $camaba->nim,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil dikirim ke '.$camaba->email,
        ]);
    }

    /**
     * NIM terbesar (lexicographic max) pada `pmb_camaba` untuk kombinasi prodi + periode PMB yang sama
     * (lewat `pmb_pendaftaran` + `pmb_daftar_ulang`).
     */
    public function lastNim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_prodi' => ['required', 'integer', 'exists:prodi,id'],
            'id_periode' => ['required', 'integer', 'exists:pmb_periode,id'],
        ]);

        $last = PmbCamaba::query()
            ->join('pmb_pendaftaran', 'pmb_pendaftaran.id_camaba', '=', 'pmb_camaba.id')
            ->join('pmb_daftar_ulang', 'pmb_daftar_ulang.id_pendaftaran', '=', 'pmb_pendaftaran.id')
            ->whereNull('pmb_pendaftaran.deleted_at')
            ->whereNull('pmb_daftar_ulang.deleted_at')
            ->where('pmb_pendaftaran.id_periode', $validated['id_periode'])
            ->where('pmb_daftar_ulang.id_prodi', $validated['id_prodi'])
            ->whereNotNull('pmb_camaba.nim')
            ->where('pmb_camaba.nim', '!=', '')
            ->max('pmb_camaba.nim');

        return response()->json([
            'last_nim' => $last,
        ]);
    }

    /**
     * Set NIM pada camaba dan buat baris `mahasiswa` (status akademik Aktif).
     */
    public function transferToMahasiswa(Request $request, PmbDaftarUlang $pmb_daftar_ulang): JsonResponse
    {
        $pmb_daftar_ulang->loadMissing(['pendaftaran.periode', 'pendaftaran.camaba', 'pendaftaran', 'prodi']);

        if ($pmb_daftar_ulang->status !== 'acc') {
            return response()->json([
                'message' => 'Transfer hanya untuk daftar ulang dengan status ACC.',
            ], 422);
        }

        $pendaftaran = $pmb_daftar_ulang->pendaftaran;
        $camaba = $pendaftaran?->camaba;
        if ($camaba === null) {
            return response()->json([
                'message' => 'Data camaba tidak ditemukan.',
            ], 422);
        }

        $validated = $request->validate([
            'nim' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mahasiswa', 'nim'),
                Rule::unique('pmb_camaba', 'nim')->ignore($camaba->id),
            ],
        ]);

        $nim = trim($validated['nim']);

        if ($camaba->email !== null && $camaba->email !== '' && Mahasiswa::where('email', $camaba->email)->exists()) {
            return response()->json([
                'message' => 'Email camaba ini sudah digunakan pada data mahasiswa.',
            ], 422);
        }

        $statusAktif = StatusAkademik::query()
            ->whereRaw('LOWER(nama) = ?', ['aktif'])
            ->first();
        if ($statusAktif === null) {
            return response()->json([
                'message' => 'Status akademik "Aktif" tidak ditemukan di sistem.',
            ], 500);
        }

        $idSemesterMasuk = Semester::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');
        if ($idSemesterMasuk === null) {
            $idSemesterMasuk = Semester::query()->orderByDesc('id')->value('id');
        }

        $mahasiswa = DB::transaction(function () use ($pmb_daftar_ulang, $camaba, $pendaftaran, $nim, $statusAktif, $idSemesterMasuk): Mahasiswa {
            $camaba->update(['nim' => $nim]);

            return Mahasiswa::create([
                'id_user' => null,
                'nama' => $camaba->nama,
                'nim' => $nim,
                'email' => $camaba->email,
                'no_wa' => $camaba->no_wa,
                'handphone' => $camaba->no_hp,
                'alamat' => $camaba->alamat,
                'kode_pos' => $camaba->kode_pos,
                'id_semester_masuk' => $idSemesterMasuk,
                'id_prodi' => $pmb_daftar_ulang->id_prodi,
                'id_kecamatan' => $camaba->id_kecamatan,
                'id_kota' => $camaba->id_kota,
                'id_provinsi' => $camaba->id_provinsi,
                'id_negara' => $camaba->id_negara !== null ? (string) $camaba->id_negara : null,
                'foto' => $camaba->foto,
                'id_status_akademik' => $statusAktif->id,
                'jenis_kelamin' => $this->mapJenisKelaminCamabaToMahasiswa($camaba->jenis_kelamin),
                'id_tempat_lahir' => $camaba->id_kota_lahir,
                'tanggal_lahir' => $camaba->tanggal_lahir,
                'no_ktp' => $camaba->no_ktp,
                'sekolah_asal' => $camaba->asal_sekolah,
                'npwp' => $camaba->no_npwp,
                'rt' => $camaba->rt,
                'rw' => $camaba->rw,
                'dusun' => $camaba->dusun,
                'kelurahan' => $camaba->kelurahan,
                'penerima_kps' => null,
                'no_kps' => $camaba->no_kps,
                'ayah' => $camaba->nama_ayah,
                'ibu' => $camaba->nama_ibu,
                'wali' => $camaba->nama_wali,
                'id_jalur_masuk' => $pendaftaran->id_jalur_masuk,
                'id_jenis_daftar' => $pendaftaran->id_jenis_daftar,
            ]);
        });

        return response()->json([
            'message' => 'Calon mahasiswa berhasil dimasukkan ke data mahasiswa.',
            'mahasiswa' => $mahasiswa->load(['prodi', 'status_akademik']),
        ], 201);
    }

    private function mapJenisKelaminCamabaToMahasiswa(?string $jk): ?string
    {
        if ($jk === null || $jk === '') {
            return null;
        }
        $l = mb_strtolower(trim($jk));

        return match (true) {
            str_contains($l, 'laki') => 'L',
            str_contains($l, 'perempuan') => 'P',
            $l === 'l' => 'L',
            $l === 'p' => 'P',
            default => null,
        };
    }
}
