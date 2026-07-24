<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Tugas;
use App\Models\TugasMahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TugasController extends Controller
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

        return \App\Models\JadwalDosen::where('id_jadwal', $jadwal->id)
            ->where('id_dosen', $dosen->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Tugas untuk satu slot jadwal (tabel tugas.id_jadwal).
     */
    public function getByJadwal(Request $request, int $jadwalId): JsonResponse
    {
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

        $tugasList = Tugas::where('id_jadwal', $jadwalId)
            ->whereNull('deleted_at')
            ->with('dosen:id,nama')
            ->orderByDesc('created_at')
            ->get();

        $tugasIds = $tugasList->pluck('id')->toArray();
        $jumlahSubmit = [];
        if ($tugasIds !== []) {
            $submitCounts = TugasMahasiswa::whereIn('id_tugas', $tugasIds)
                ->whereNull('deleted_at')
                ->selectRaw('id_tugas, COUNT(*) as jumlah')
                ->groupBy('id_tugas')
                ->pluck('jumlah', 'id_tugas')
                ->toArray();
            $jumlahSubmit = $submitCounts;
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $data = $tugasList->map(function (Tugas $tugas) use ($jumlahSubmit, $baseUrl) {
            $fileUrl = $tugas->file ? $baseUrl.'/storage/'.ltrim((string) $tugas->file, '/') : null;

            return [
                'id' => $tugas->id,
                'id_jadwal' => $tugas->id_jadwal,
                'nama' => $tugas->nama,
                'deskripsi' => $tugas->deskripsi,
                'file' => $tugas->file,
                'file_url' => $fileUrl,
                'tanggal_mulai' => $tugas->tanggal_mulai ? $tugas->tanggal_mulai->format('Y-m-d H:i:s') : null,
                'tanggal_selesai' => $tugas->tanggal_selesai ? $tugas->tanggal_selesai->format('Y-m-d H:i:s') : null,
                'jumlah_submit' => (int) ($jumlahSubmit[$tugas->id] ?? 0),
                'dosen' => $tugas->dosen ? [
                    'id' => $tugas->dosen->id,
                    'nama' => $tugas->dosen->nama,
                ] : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Daftar pengumpulan tugas mahasiswa (tugas_mahasiswa) untuk semua tugas pada satu slot jadwal — akses dosen.
     */
    public function getPengumpulanByJadwalForDosen(Request $request, int $jadwalId): JsonResponse
    {
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

        $tugasIds = Tugas::where('id_jadwal', $jadwalId)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($tugasIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $namaTugasById = Tugas::whereIn('id', $tugasIds)->pluck('nama', 'id');

        $submissions = TugasMahasiswa::whereIn('id_tugas', $tugasIds)
            ->whereNull('deleted_at')
            ->with(['mahasiswa:id,nim,nama', 'tugas:id,nama'])
            ->orderByDesc('tanggal_submit')
            ->orderBy('id')
            ->get()
            ->sortBy(function (TugasMahasiswa $tm) {
                $nim = $tm->mahasiswa->nim ?? '';

                return [(int) $tm->id_tugas, (string) $nim];
            })
            ->values();

        $baseUrl = rtrim((string) config('app.url'), '/');
        $data = $submissions->map(function (TugasMahasiswa $tm) use ($baseUrl, $namaTugasById) {
            $fileUrl = $tm->file ? $baseUrl.'/storage/'.ltrim((string) $tm->file, '/') : null;

            return [
                'id' => $tm->id,
                'id_tugas' => $tm->id_tugas,
                'nama_tugas' => $namaTugasById[$tm->id_tugas] ?? ($tm->tugas->nama ?? ''),
                'mahasiswa' => $tm->mahasiswa ? [
                    'id' => $tm->mahasiswa->id,
                    'nim' => $tm->mahasiswa->nim,
                    'nama' => $tm->mahasiswa->nama,
                ] : null,
                'tanggal_submit' => $tm->tanggal_submit ? $tm->tanggal_submit->format('Y-m-d H:i:s') : null,
                'file_url' => $fileUrl,
                'keterangan' => $tm->keterangan,
                'status' => $tm->status ?: 'submitted',
            ];
        });

        return response()->json(['data' => $data->values()->all()]);
    }

    /**
     * Dosen mengubah status pengumpulan tugas mahasiswa (mis. menjadi accepted).
     */
    public function updatePengumpulanStatusForDosen(Request $request, int $idTugasMahasiswa): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted'],
        ]);

        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $tm = TugasMahasiswa::whereNull('deleted_at')
            ->with(['tugas' => function ($q): void {
                $q->whereNull('deleted_at');
            }])
            ->find($idTugasMahasiswa);

        if (! $tm || ! $tm->tugas) {
            return response()->json(['message' => 'Pengumpulan tugas tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::whereNull('deleted_at')->find($tm->tugas->id_jadwal);
        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke pengumpulan ini'], 403);
        }

        $tm->status = $validated['status'];
        $tm->updated_by = $dosen->nama;
        $tm->save();

        return response()->json([
            'message' => 'Status pengumpulan diperbarui',
            'status' => $tm->status,
        ]);
    }

    /**
     * Daftar tugas untuk satu slot jadwal — akses mahasiswa yang punya KRS pada kelas jadwal tersebut.
     */
    public function getByJadwalForMahasiswa(Request $request, int $jadwalId): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();
        if (! $mahasiswa) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::with('kelas')->whereNull('deleted_at')->find($jadwalId);
        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        $hasKrs = Krs::where('id_mahasiswa', $mahasiswa->id)
            ->where('id_kelas', $jadwal->id_kelas)
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasKrs) {
            return response()->json(['message' => 'Anda tidak memiliki KRS untuk kelas pada jadwal ini'], 403);
        }

        $tugasList = Tugas::where('id_jadwal', $jadwalId)
            ->whereNull('deleted_at')
            ->with('dosen:id,nama')
            ->orderByDesc('created_at')
            ->get();

        $tugasIds = $tugasList->pluck('id')->toArray();
        $mySubmits = collect();
        if ($tugasIds !== []) {
            $mySubmits = TugasMahasiswa::whereIn('id_tugas', $tugasIds)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id_tugas');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $data = $tugasList->map(function (Tugas $tugas) use ($mySubmits, $baseUrl) {
            /** @var TugasMahasiswa|null $submit */
            $submit = $mySubmits->get($tugas->id);
            $fileUrl = $tugas->file ? $baseUrl.'/storage/'.ltrim((string) $tugas->file, '/') : null;
            $submitFileUrl = $submit && $submit->file
                ? $baseUrl.'/storage/'.ltrim((string) $submit->file, '/')
                : null;

            return [
                'id' => $tugas->id,
                'id_jadwal' => $tugas->id_jadwal,
                'nama' => $tugas->nama,
                'deskripsi' => $tugas->deskripsi,
                'file_url' => $fileUrl,
                'tanggal_mulai' => $tugas->tanggal_mulai ? $tugas->tanggal_mulai->format('Y-m-d H:i:s') : null,
                'tanggal_selesai' => $tugas->tanggal_selesai ? $tugas->tanggal_selesai->format('Y-m-d H:i:s') : null,
                'dosen' => $tugas->dosen ? [
                    'id' => $tugas->dosen->id,
                    'nama' => $tugas->dosen->nama,
                ] : null,
                'pengumpulan_saya' => $submit ? [
                    'tanggal_submit' => $submit->tanggal_submit ? $submit->tanggal_submit->format('Y-m-d H:i:s') : null,
                    'file_url' => $submitFileUrl,
                    'keterangan' => $submit->keterangan,
                    'status' => $submit->status ?: 'submitted',
                ] : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Mahasiswa mengunggah / memperbarui pengumpulan tugas (tabel tugas_mahasiswa).
     */
    public function submitKumpulkanMahasiswa(Request $request, int $idTugas): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();
        if (! $mahasiswa) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan'], 404);
        }

        $tugas = Tugas::whereNull('deleted_at')->find($idTugas);
        if (! $tugas) {
            return response()->json(['message' => 'Tugas tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::whereNull('deleted_at')->find($tugas->id_jadwal);
        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tugas tidak ditemukan'], 404);
        }

        $hasKrs = Krs::where('id_mahasiswa', $mahasiswa->id)
            ->where('id_kelas', $jadwal->id_kelas)
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasKrs) {
            return response()->json(['message' => 'Anda tidak memiliki KRS untuk kelas pada tugas ini'], 403);
        }

        $now = now();

        if ($tugas->tanggal_mulai && $now->lt($tugas->tanggal_mulai)) {
            return response()->json(['message' => 'Pengumpulan tugas belum dibuka'], 422);
        }

        if ($tugas->tanggal_selesai && $now->gt($tugas->tanggal_selesai)) {
            return response()->json(['message' => 'Masa pengumpulan tugas telah berakhir'], 422);
        }

        $validated = $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'keterangan' => ['nullable', 'string', 'max:65535'],
        ]);

        $tm = TugasMahasiswa::withTrashed()
            ->where('id_tugas', $tugas->id)
            ->where('id_mahasiswa', $mahasiswa->id)
            ->first();

        if ($tm && $tm->trashed()) {
            $tm->restore();
        }

        if (! $tm) {
            $tm = new TugasMahasiswa;
            $tm->id_tugas = $tugas->id;
            $tm->id_mahasiswa = $mahasiswa->id;
        }

        $fileAlready = (bool) $tm->file;
        if (! $fileAlready && ! $request->hasFile('file')) {
            return response()->json(['message' => 'Berkas tugas wajib diunggah'], 422);
        }

        if ($request->hasFile('file')) {
            if ($tm->file) {
                Storage::disk('public')->delete($tm->file);
            }
            $file = $request->file('file');
            $filename = 'tugas_mhs_'.$mahasiswa->id.'_'.$tugas->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $tm->file = $file->storeAs('tugas_mahasiswa', $filename, 'public');
        }

        if (array_key_exists('keterangan', $validated)) {
            $tm->keterangan = $validated['keterangan'];
        }

        $tm->tanggal_submit = $now;
        $tm->status = 'submitted';

        if (! $tm->exists) {
            $tm->created_by = $mahasiswa->nama;
        }
        $tm->updated_by = $mahasiswa->nama;

        $tm->save();

        $baseUrl = rtrim((string) config('app.url'), '/');
        $submitFileUrl = $tm->file
            ? $baseUrl.'/storage/'.ltrim((string) $tm->file, '/')
            : null;

        return response()->json([
            'message' => 'Pengumpulan berhasil disimpan',
            'pengumpulan_saya' => [
                'tanggal_submit' => $tm->tanggal_submit ? $tm->tanggal_submit->format('Y-m-d H:i:s') : null,
                'file_url' => $submitFileUrl,
                'keterangan' => $tm->keterangan,
                'status' => $tm->status ?: 'submitted',
            ],
        ], 200);
    }

    /**
     * Get list of tugas by kelas
     */
    public function getByKelas(Request $request, int $idKelas): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        // Verifikasi bahwa dosen memiliki akses ke kelas ini
        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi',
            'prodi.jenjang',
            'semester',
        ])->find($idKelas);

        if (! $kelas) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan',
            ], 404);
        }

        // Cek apakah dosen adalah dosen PIC atau memiliki jadwal di kelas ini
        $hasAccess = false;
        if ($kelas->id_dosen_pic === $dosen->id) {
            $hasAccess = true;
        } else {
            $hasJadwal = \App\Models\JadwalDosen::whereHas('jadwal', function ($q) use ($idKelas) {
                $q->where('id_kelas', $idKelas);
            })
                ->where('id_dosen', $dosen->id)
                ->where('status', 'active')
                ->exists();

            if ($hasJadwal) {
                $hasAccess = true;
            }
        }

        if (! $hasAccess) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kelas ini',
            ], 403);
        }

        // Ambil tugas untuk semua jadwal di kelas ini (tugas.id_jadwal)
        $tugasList = Tugas::whereHas('jadwal', function ($q) use ($idKelas): void {
            $q->where('id_kelas', $idKelas);
        })
            ->whereNull('deleted_at')
            ->with('dosen:id,nama')
            ->orderByDesc('created_at')
            ->get();

        // Hitung jumlah mahasiswa yang sudah mengumpulkan untuk setiap tugas
        $tugasIds = $tugasList->pluck('id')->toArray();
        $jumlahSubmit = [];
        if (! empty($tugasIds)) {
            $submitCounts = TugasMahasiswa::whereIn('id_tugas', $tugasIds)
                ->whereNull('deleted_at')
                ->selectRaw('id_tugas, COUNT(*) as jumlah')
                ->groupBy('id_tugas')
                ->pluck('jumlah', 'id_tugas')
                ->toArray();
            $jumlahSubmit = $submitCounts;
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $data = $tugasList->map(function (Tugas $tugas) use ($jumlahSubmit, $baseUrl) {
            $fileUrl = $tugas->file ? $baseUrl.'/storage/'.ltrim((string) $tugas->file, '/') : null;

            return [
                'id' => $tugas->id,
                'id_jadwal' => $tugas->id_jadwal,
                'nama' => $tugas->nama,
                'deskripsi' => $tugas->deskripsi,
                'file' => $tugas->file,
                'file_url' => $fileUrl,
                'tanggal_mulai' => $tugas->tanggal_mulai ? $tugas->tanggal_mulai->format('Y-m-d H:i:s') : null,
                'tanggal_selesai' => $tugas->tanggal_selesai ? $tugas->tanggal_selesai->format('Y-m-d H:i:s') : null,
                'jumlah_submit' => (int) ($jumlahSubmit[$tugas->id] ?? 0),
                'dosen' => $tugas->dosen ? [
                    'id' => $tugas->dosen->id,
                    'nama' => $tugas->dosen->nama,
                ] : null,
            ];
        });

        // Ambil data mata kuliah dari kurikulum_matkul dengan fallback ke matkul
        $kurikulumMatkul = $kelas->kurikulumMatkul;
        $matkul = $kurikulumMatkul?->matkul;

        // Kode mata kuliah: prioritas dari kurikulum_matkul, jika kosong ambil dari matkul
        $kodeMatkul = (! empty($kurikulumMatkul?->kode_matkul) && trim($kurikulumMatkul->kode_matkul) !== '')
            ? $kurikulumMatkul->kode_matkul
            : ($matkul?->kode ?? '-');

        // Nama mata kuliah: prioritas dari kurikulum_matkul, jika kosong ambil dari matkul
        $namaMatkul = (! empty($kurikulumMatkul?->nama_matkul) && trim($kurikulumMatkul->nama_matkul) !== '')
            ? $kurikulumMatkul->nama_matkul
            : ($matkul?->nama ?? '-');

        // SKS: prioritas dari kurikulum_matkul, jika kosong atau 0 ambil dari matkul
        $sks = (! empty($kurikulumMatkul?->sks) && $kurikulumMatkul->sks > 0)
            ? $kurikulumMatkul->sks
            : ($matkul?->sks ?? 0);

        return response()->json([
            'kelas' => [
                'id' => $kelas->id,
                'nama' => $kelas->nama ?? '-',
                'kode_matkul' => $kodeMatkul,
                'nama_matkul' => $namaMatkul,
                'sks' => $sks,
                'prodi' => $kelas->prodi ? [
                    'id' => $kelas->prodi->id,
                    'nama' => $kelas->prodi->nama,
                ] : null,
            ],
            'data' => $data,
        ]);
    }

    /**
     * Store new tugas
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal,id'],
            'id_kelas' => ['nullable', 'integer', 'exists:kelas,id'],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        if (empty($validated['id_jadwal']) && empty($validated['id_kelas'])) {
            return response()->json([
                'message' => 'id_jadwal atau id_kelas wajib diisi',
            ], 422);
        }

        if (! empty($validated['id_jadwal'])) {
            $jadwal = Jadwal::with('kelas')->find($validated['id_jadwal']);
        } else {
            $jadwal = Jadwal::with('kelas')
                ->where('id_kelas', $validated['id_kelas'])
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();
        }

        if (! $jadwal) {
            return response()->json([
                'message' => ! empty($validated['id_jadwal']) ? 'Jadwal tidak ditemukan' : 'Kelas belum memiliki jadwal',
            ], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke jadwal/kelas ini',
            ], 403);
        }

        // Handle file upload
        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = 'tugas_j'.$jadwal->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('tugas', $filename, 'public');
        }

        $tugas = Tugas::create([
            'id_jadwal' => $jadwal->id,
            'id_dosen' => $dosen->id,
            'nama' => $validated['nama'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'file' => $filePath,
            'tanggal_mulai' => $validated['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $validated['tanggal_selesai'] ?? null,
        ]);

        $baseUrl = config('app.url');
        $fileUrl = $tugas->file ? $baseUrl.'/storage/'.$tugas->file : null;

        return response()->json([
            'id' => $tugas->id,
            'nama' => $tugas->nama,
            'deskripsi' => $tugas->deskripsi,
            'file' => $tugas->file,
            'file_url' => $fileUrl,
            'tanggal_mulai' => $tugas->tanggal_mulai ? $tugas->tanggal_mulai->format('Y-m-d H:i:s') : null,
            'tanggal_selesai' => $tugas->tanggal_selesai ? $tugas->tanggal_selesai->format('Y-m-d H:i:s') : null,
            'jumlah_submit' => 0,
        ], 201);
    }

    /**
     * Update tugas
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $tugas = Tugas::find($id);
        if (! $tugas) {
            return response()->json([
                'message' => 'Tugas tidak ditemukan',
            ], 404);
        }

        // Verifikasi bahwa tugas milik dosen ini
        if ($tugas->id_dosen !== $dosen->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke tugas ini',
            ], 403);
        }

        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            // Hapus file lama jika ada
            if ($tugas->file) {
                Storage::disk('public')->delete($tugas->file);
            }

            $file = $request->file('file');
            $filename = 'tugas_j'.$tugas->id_jadwal.'_'.time().'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('tugas', $filename, 'public');
            $validated['file'] = $filePath;
        }

        $tugas->update($validated);

        $baseUrl = config('app.url');
        $fileUrl = $tugas->file ? $baseUrl.'/storage/'.$tugas->file : null;

        $jumlahSubmit = TugasMahasiswa::where('id_tugas', $tugas->id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'id' => $tugas->id,
            'nama' => $tugas->nama,
            'deskripsi' => $tugas->deskripsi,
            'file' => $tugas->file,
            'file_url' => $fileUrl,
            'tanggal_mulai' => $tugas->tanggal_mulai ? $tugas->tanggal_mulai->format('Y-m-d H:i:s') : null,
            'tanggal_selesai' => $tugas->tanggal_selesai ? $tugas->tanggal_selesai->format('Y-m-d H:i:s') : null,
            'jumlah_submit' => $jumlahSubmit,
        ]);
    }

    /**
     * Delete tugas
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

        $tugas = Tugas::find($id);
        if (! $tugas) {
            return response()->json([
                'message' => 'Tugas tidak ditemukan',
            ], 404);
        }

        // Verifikasi bahwa tugas milik dosen ini
        if ($tugas->id_dosen !== $dosen->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke tugas ini',
            ], 403);
        }

        // Cek apakah sudah ada mahasiswa yang mengumpulkan
        $jumlahSubmit = TugasMahasiswa::where('id_tugas', $tugas->id)
            ->whereNull('deleted_at')
            ->count();

        if ($jumlahSubmit > 0) {
            return response()->json([
                'message' => 'Tugas tidak dapat dihapus karena sudah ada mahasiswa yang mengumpulkan',
            ], 422);
        }

        // Hapus file jika ada
        if ($tugas->file) {
            Storage::disk('public')->delete($tugas->file);
        }

        $tugas->delete();

        return response()->json([
            'message' => 'Tugas berhasil dihapus',
        ]);
    }
}
