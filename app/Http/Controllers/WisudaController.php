<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WisudaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = Wisuda::query()->withCount('wisudaMahasiswa as jumlah_mahasiswa');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('keterangan', 'like', '%'.$search.'%');
            });
        }

        $data = $query->orderByDesc('tanggal_wisuda')->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_wisuda' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $validated['nama'] = trim($validated['nama']);
        if (! isset($validated['status']) || $validated['status'] === '') {
            $validated['status'] = 'inactive';
        }

        $duplicate = Wisuda::query()
            ->where('nama', $validated['nama'])
            ->whereDate('tanggal_wisuda', $validated['tanggal_wisuda'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'nama' => ['Sudah ada wisuda dengan nama dan tanggal wisuda yang sama.'],
            ]);
        }

        $user = $request->user();
        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
        $validated['created_by'] = $by;
        $validated['updated_by'] = $by;

        $wisuda = Wisuda::create($validated);
        $wisuda->loadCount('wisudaMahasiswa as jumlah_mahasiswa');

        return response()->json($wisuda, 201);
    }

    public function show(Wisuda $wisuda): JsonResponse
    {
        $wisuda->loadCount('wisudaMahasiswa as jumlah_mahasiswa');
        $wisuda->load([
            'wisudaMahasiswa' => static fn ($q) => $q->orderBy('id'),
            'wisudaMahasiswa.mahasiswa.prodi.jenjang',
        ]);

        $peserta = $wisuda->wisudaMahasiswa->map(static function (WisudaMahasiswa $row) {
            $m = $row->mahasiswa;

            return [
                'id' => $row->id,
                'id_mahasiswa' => $row->id_mahasiswa,
                'nim' => $m?->nim,
                'nama' => $m?->nama,
                'prodi' => $m?->prodi ? [
                    'id' => $m->prodi->id,
                    'nama' => $m->prodi->nama,
                    'kode' => $m->prodi->kode ?? null,
                    'jenjang' => $m->prodi->jenjang ? [
                        'id' => $m->prodi->jenjang->id,
                        'nama' => $m->prodi->jenjang->nama,
                        'kode' => $m->prodi->jenjang->kode ?? null,
                    ] : null,
                ] : null,
                'no_sk_wisuda' => $row->no_sk_wisuda,
                'tanggal_sk_wisuda' => $row->tanggal_sk_wisuda,
                'file_sk_wisuda' => $row->file_sk_wisuda,
                'foto' => $row->foto,
                'status' => $row->status,
            ];
        })->values();

        return response()->json([
            'id' => $wisuda->id,
            'nama' => $wisuda->nama,
            'tanggal_wisuda' => $wisuda->tanggal_wisuda?->format('Y-m-d'),
            'keterangan' => $wisuda->keterangan,
            'status' => $wisuda->status,
            'jumlah_mahasiswa' => (int) $wisuda->jumlah_mahasiswa,
            'peserta' => $peserta,
        ]);
    }

    public function update(Request $request, Wisuda $wisuda): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'tanggal_wisuda' => ['sometimes', 'required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        if (array_key_exists('nama', $validated)) {
            $validated['nama'] = trim($validated['nama']);
        }

        $namaBaru = $validated['nama'] ?? $wisuda->nama;
        $tanggalBaru = $validated['tanggal_wisuda'] ?? $wisuda->tanggal_wisuda?->format('Y-m-d');
        $duplicate = Wisuda::query()
            ->where('nama', $namaBaru)
            ->whereDate('tanggal_wisuda', $tanggalBaru)
            ->where('id', '!=', $wisuda->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'nama' => ['Sudah ada wisuda lain dengan nama dan tanggal wisuda yang sama.'],
            ]);
        }

        $user = $request->user();
        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
        $validated['updated_by'] = $by;

        $wisuda->update($validated);
        $wisuda->refresh();
        $wisuda->loadCount('wisudaMahasiswa as jumlah_mahasiswa');

        return response()->json($wisuda);
    }

    public function destroy(Request $request, Wisuda $wisuda): JsonResponse
    {
        $user = $request->user();
        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
        $wisuda->deleted_by = $by;
        $wisuda->save();
        $wisuda->delete();

        return response()->json(['message' => 'Data wisuda dihapus']);
    }

    /**
     * Mahasiswa ber-yudisium yang belum terdaftar sebagai peserta wisuda ini.
     */
    public function eligibleMahasiswa(Request $request, Wisuda $wisuda): JsonResponse
    {
        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $idProdi = $request->get('id_prodi');
        $search = $request->get('search');

        $query = Mahasiswa::query()
            ->whereNull('mahasiswa.deleted_at')
            ->whereExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('yudisium')
                    ->whereColumn('yudisium.id_mahasiswa', 'mahasiswa.id')
                    ->whereNull('yudisium.deleted_at');
            })
            ->whereNotExists(function ($q) use ($wisuda): void {
                $q->selectRaw('1')
                    ->from('wisuda_mahasiswa')
                    ->whereColumn('wisuda_mahasiswa.id_mahasiswa', 'mahasiswa.id')
                    ->where('wisuda_mahasiswa.id_wisuda', $wisuda->id)
                    ->whereNull('wisuda_mahasiswa.deleted_at');
            })
            ->with(['prodi:id,nama,kode']);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
                if ($idProdi !== null && $idProdi !== '' && ! in_array((int) $idProdi, $allowedProdiIds, true)) {
                    $idProdi = null;
                }
            }
        }

        if ($idProdi !== null && $idProdi !== '') {
            $query->where('mahasiswa.id_prodi', (int) $idProdi);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('mahasiswa.nama', 'like', $term)
                    ->orWhere('mahasiswa.nim', 'like', $term);
            });
        }

        $paginated = $query->orderBy('mahasiswa.nama')->paginate($perPage);

        return response()->json($paginated);
    }

    /**
     * Daftarkan mahasiswa sebagai peserta wisuda (wisuda_mahasiswa).
     */
    public function storePeserta(Request $request, Wisuda $wisuda): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => [
                'required',
                'integer',
                Rule::exists('mahasiswa', 'id')->where(static fn ($q) => $q->whereNull('deleted_at')),
            ],
            'no_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'file_sk_wisuda' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'acc', 'approved', 'rejected'])],
        ]);

        $idMahasiswa = (int) $validated['id_mahasiswa'];

        $mahasiswa = Mahasiswa::query()->whereKey($idMahasiswa)->whereNull('deleted_at')->first();
        if (! $mahasiswa) {
            throw ValidationException::withMessages([
                'id_mahasiswa' => ['Mahasiswa tidak ditemukan.'],
            ]);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
            }
        }

        $hasYudisium = Yudisium::query()
            ->where('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->exists();
        if (! $hasYudisium) {
            throw ValidationException::withMessages([
                'id_mahasiswa' => ['Mahasiswa harus memiliki data yudisium terlebih dahulu.'],
            ]);
        }

        $active = WisudaMahasiswa::query()
            ->where('id_wisuda', $wisuda->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->first();
        if ($active) {
            throw ValidationException::withMessages([
                'id_mahasiswa' => ['Mahasiswa sudah terdaftar pada wisuda ini.'],
            ]);
        }

        $user = $request->user();
        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';

        $payload = [
            'id_wisuda' => $wisuda->id,
            'id_mahasiswa' => $idMahasiswa,
            'no_sk_wisuda' => $validated['no_sk_wisuda'] ?? null,
            'tanggal_sk_wisuda' => $validated['tanggal_sk_wisuda'] ?? null,
            'file_sk_wisuda' => $validated['file_sk_wisuda'] ?? null,
            'foto' => $validated['foto'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'updated_by' => $by,
        ];

        $trashed = WisudaMahasiswa::onlyTrashed()
            ->where('id_wisuda', $wisuda->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->deleted_by = null;
            $trashed->fill($payload);
            $trashed->save();
            $row = $trashed->fresh(['mahasiswa.prodi']);
        } else {
            $payload['created_by'] = $by;
            $row = WisudaMahasiswa::create($payload)->load('mahasiswa.prodi');
        }

        return response()->json($row, 201);
    }

    /**
     * Detail satu peserta: ringkasan wisuda, data peserta, mahasiswa, dan yudisium.
     */
    public function showPeserta(Request $request, Wisuda $wisuda, WisudaMahasiswa $peserta): JsonResponse
    {
        if ((int) $peserta->id_wisuda !== (int) $wisuda->id) {
            return response()->json(['message' => 'Data peserta tidak ditemukan untuk wisuda ini.'], 404);
        }

        $peserta->load(['mahasiswa.prodi.jenjang']);
        $m = $peserta->mahasiswa;

        $yudisium = Yudisium::query()
            ->with('jenis_keluar:id,nama')
            ->where('id_mahasiswa', $peserta->id_mahasiswa)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $m && ! in_array((int) $m->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }

        return response()->json([
            'wisuda' => [
                'id' => $wisuda->id,
                'nama' => $wisuda->nama,
                'tanggal_wisuda' => $wisuda->tanggal_wisuda?->format('Y-m-d'),
                'keterangan' => $wisuda->keterangan,
                'status' => $wisuda->status,
            ],
            'peserta' => [
                'id' => $peserta->id,
                'id_mahasiswa' => $peserta->id_mahasiswa,
                'no_sk_wisuda' => $peserta->no_sk_wisuda,
                'tanggal_sk_wisuda' => $peserta->tanggal_sk_wisuda,
                'file_sk_wisuda' => $peserta->file_sk_wisuda,
                'file_sk_wisuda_url' => $this->publicFileUrl($peserta->file_sk_wisuda),
                'foto' => $peserta->foto,
                'foto_url' => $this->publicFileUrl($peserta->foto),
                'status' => $peserta->status,
            ],
            'mahasiswa' => $m ? [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->nama,
                'prodi' => $m->prodi ? [
                    'id' => $m->prodi->id,
                    'nama' => $m->prodi->nama,
                    'kode' => $m->prodi->kode ?? null,
                    'jenjang' => $m->prodi->jenjang ? [
                        'id' => $m->prodi->jenjang->id,
                        'nama' => $m->prodi->jenjang->nama,
                        'kode' => $m->prodi->jenjang->kode ?? null,
                    ] : null,
                ] : null,
            ] : null,
            'yudisium' => $yudisium ? [
                'id' => $yudisium->id,
                'tgl_keluar' => $yudisium->tgl_keluar,
                'no_ijazah' => $yudisium->no_ijazah,
                'no_sk_yudisium' => $yudisium->no_sk_yudisium,
                'tanggal_sk_yudisium' => $yudisium->tanggal_sk_yudisium,
                'ipk' => $yudisium->ipk,
                'judul_skripsi' => $yudisium->judul_skripsi,
                'keterangan' => $yudisium->keterangan,
                'jenis_keluar' => $yudisium->jenis_keluar ? [
                    'id' => $yudisium->jenis_keluar->id,
                    'nama' => $yudisium->jenis_keluar->nama,
                ] : null,
            ] : null,
        ]);
    }

    /**
     * Perbarui data administrasi peserta wisuda (SK, foto, status).
     */
    public function updatePeserta(Request $request, Wisuda $wisuda, WisudaMahasiswa $peserta): JsonResponse
    {
        if ((int) $peserta->id_wisuda !== (int) $wisuda->id) {
            return response()->json(['message' => 'Data peserta tidak ditemukan untuk wisuda ini.'], 404);
        }

        $peserta->load('mahasiswa');
        $m = $peserta->mahasiswa;

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $m && ! in_array((int) $m->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }

        $validated = $request->validate([
            'no_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'file_sk_wisuda' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'acc', 'approved', 'rejected'])],
        ]);

        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
        $validated['updated_by'] = $by;

        $peserta->fill($validated);
        $peserta->save();
        $peserta->refresh();
        $peserta->load(['mahasiswa.prodi.jenjang']);

        return response()->json([
            'message' => 'Data peserta wisuda diperbarui.',
            'wisuda' => [
                'id' => $wisuda->id,
                'nama' => $wisuda->nama,
                'tanggal_wisuda' => $wisuda->tanggal_wisuda?->format('Y-m-d'),
                'keterangan' => $wisuda->keterangan,
                'status' => $wisuda->status,
            ],
            'peserta' => [
                'id' => $peserta->id,
                'id_mahasiswa' => $peserta->id_mahasiswa,
                'no_sk_wisuda' => $peserta->no_sk_wisuda,
                'tanggal_sk_wisuda' => $peserta->tanggal_sk_wisuda,
                'file_sk_wisuda' => $peserta->file_sk_wisuda,
                'file_sk_wisuda_url' => $this->publicFileUrl($peserta->file_sk_wisuda),
                'foto' => $peserta->foto,
                'foto_url' => $this->publicFileUrl($peserta->foto),
                'status' => $peserta->status,
            ],
        ]);
    }

    /**
     * Hapus (soft delete) peserta wisuda dari baris wisuda_mahasiswa.
     */
    public function destroyPeserta(Request $request, Wisuda $wisuda, WisudaMahasiswa $peserta): JsonResponse
    {
        if ((int) $peserta->id_wisuda !== (int) $wisuda->id) {
            return response()->json(['message' => 'Data peserta tidak ditemukan untuk wisuda ini.'], 404);
        }

        $peserta->loadMissing('mahasiswa');

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $peserta->mahasiswa && ! in_array((int) $peserta->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }

        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
        $peserta->deleted_by = $by;
        $peserta->save();
        $peserta->delete();

        return response()->json(['message' => 'Mahasiswa dihapus dari daftar peserta wisuda.']);
    }

    /**
     * Ringkasan yudisium dan wisuda untuk mahasiswa login.
     */
    public function getMyYudisiumWisuda(Request $request): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::query()
            ->where('id_user', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $yudisium = Yudisium::query()
            ->with('jenis_keluar:id,nama')
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();

        $wisudaMahasiswa = WisudaMahasiswa::query()
            ->with('wisuda:id,nama,tanggal_wisuda,keterangan,status')
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        $jadwalWisudaAktif = Wisuda::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('tanggal_wisuda')
            ->get(['id', 'nama', 'tanggal_wisuda', 'keterangan', 'status']);

        return response()->json([
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
            ],
            'yudisium' => $yudisium ? [
                'id' => $yudisium->id,
                'tgl_keluar' => $yudisium->tgl_keluar,
                'no_ijazah' => $yudisium->no_ijazah,
                'no_sk_yudisium' => $yudisium->no_sk_yudisium,
                'tanggal_sk_yudisium' => $yudisium->tanggal_sk_yudisium,
                'ipk' => $yudisium->ipk,
                'judul_skripsi' => $yudisium->judul_skripsi,
                'keterangan' => $yudisium->keterangan,
                'jenis_keluar' => $yudisium->jenis_keluar ? [
                    'id' => $yudisium->jenis_keluar->id,
                    'nama' => $yudisium->jenis_keluar->nama,
                ] : null,
            ] : null,
            'wisuda' => $wisudaMahasiswa ? [
                'id' => $wisudaMahasiswa->id,
                'status' => $wisudaMahasiswa->status,
                'no_sk_wisuda' => $wisudaMahasiswa->no_sk_wisuda,
                'tanggal_sk_wisuda' => $wisudaMahasiswa->tanggal_sk_wisuda,
                'file_sk_wisuda' => $wisudaMahasiswa->file_sk_wisuda,
                'file_sk_wisuda_url' => $this->publicFileUrl($wisudaMahasiswa->file_sk_wisuda),
                'foto' => $wisudaMahasiswa->foto,
                'foto_url' => $this->publicFileUrl($wisudaMahasiswa->foto),
                'jadwal' => $wisudaMahasiswa->wisuda ? [
                    'id' => $wisudaMahasiswa->wisuda->id,
                    'nama' => $wisudaMahasiswa->wisuda->nama,
                    'tanggal_wisuda' => $wisudaMahasiswa->wisuda->tanggal_wisuda?->format('Y-m-d'),
                    'keterangan' => $wisudaMahasiswa->wisuda->keterangan,
                    'status' => $wisudaMahasiswa->wisuda->status,
                ] : null,
            ] : null,
            'jadwal_wisuda_aktif' => $jadwalWisudaAktif->map(static function (Wisuda $w): array {
                return [
                    'id' => $w->id,
                    'nama' => $w->nama,
                    'tanggal_wisuda' => $w->tanggal_wisuda?->format('Y-m-d'),
                    'keterangan' => $w->keterangan,
                    'status' => $w->status,
                ];
            })->values(),
        ]);
    }

    /**
     * Mahasiswa mendaftar sebagai peserta wisuda (status selalu pending).
     */
    public function daftarWisudaMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::query()
            ->where('id_user', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $hasYudisium = Yudisium::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasYudisium) {
            return response()->json([
                'message' => 'Anda belum memiliki data yudisium, sehingga belum bisa mendaftar wisuda.',
            ], 422);
        }

        $alreadyRegistered = WisudaMahasiswa::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->exists();
        if ($alreadyRegistered) {
            return response()->json([
                'message' => 'Anda sudah terdaftar sebagai peserta wisuda.',
            ], 422);
        }

        $validated = $request->validate([
            'id_wisuda' => [
                'required',
                'integer',
                Rule::exists('wisuda', 'id')->where(static fn ($q) => $q->whereNull('deleted_at')->where('status', 'active')),
            ],
        ]);

        $wisuda = Wisuda::query()
            ->whereKey((int) $validated['id_wisuda'])
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->first();

        if (! $wisuda) {
            return response()->json([
                'message' => 'Jadwal wisuda tidak valid atau tidak aktif.',
            ], 422);
        }

        $by = $user->name ?? (string) ($user->email ?? $user->id);

        $row = WisudaMahasiswa::create([
            'id_mahasiswa' => $mahasiswa->id,
            'id_wisuda' => $wisuda->id,
            'status' => 'pending',
            'created_by' => $by,
            'updated_by' => $by,
        ])->load('wisuda:id,nama,tanggal_wisuda,keterangan,status');

        return response()->json([
            'message' => 'Pendaftaran wisuda berhasil dikirim. Status Anda saat ini: pending.',
            'data' => [
                'id' => $row->id,
                'status' => $row->status,
                'jadwal' => $row->wisuda ? [
                    'id' => $row->wisuda->id,
                    'nama' => $row->wisuda->nama,
                    'tanggal_wisuda' => $row->wisuda->tanggal_wisuda?->format('Y-m-d'),
                    'keterangan' => $row->wisuda->keterangan,
                    'status' => $row->wisuda->status,
                ] : null,
            ],
        ], 201);
    }

    /**
     * Upload foto wisuda oleh mahasiswa login.
     */
    public function uploadMyFotoWisuda(Request $request): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::query()
            ->where('id_user', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $wisudaMahasiswa = WisudaMahasiswa::query()
            ->with('wisuda:id,nama,tanggal_wisuda,keterangan,status')
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        if (! $wisudaMahasiswa || ! $wisudaMahasiswa->wisuda) {
            return response()->json([
                'message' => 'Jadwal wisuda belum tersedia. Upload foto belum dapat dilakukan.',
            ], 422);
        }

        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        if ($wisudaMahasiswa->foto) {
            Storage::disk('public')->delete($wisudaMahasiswa->foto);
        }

        $path = $request->file('foto')->store('wisuda/foto-mahasiswa', 'public');
        $wisudaMahasiswa->foto = $path;
        $wisudaMahasiswa->updated_by = $user->name ?? (string) ($user->email ?? $user->id);
        $wisudaMahasiswa->save();

        return response()->json([
            'message' => 'Foto wisuda berhasil diunggah.',
            'data' => [
                'id' => $wisudaMahasiswa->id,
                'foto' => $wisudaMahasiswa->foto,
                'foto_url' => $this->publicFileUrl($wisudaMahasiswa->foto),
            ],
        ]);
    }

    /**
     * Daftar peserta satu wisuda dilengkapi mahasiswa+prodi, terurut nama — dipakai bersama oleh export PDF & Excel.
     *
     * @param  array<int>|null  $allowedProdiIds  null = tanpa filter (akses penuh)
     */
    private function pesertaWisudaRowsForExport(Wisuda $wisuda, ?array $allowedProdiIds = null)
    {
        return WisudaMahasiswa::where('id_wisuda', $wisuda->id)
            ->whereNull('deleted_at')
            ->when($allowedProdiIds !== null, function ($q) use ($allowedProdiIds) {
                $q->whereHas('mahasiswa', function ($q2) use ($allowedProdiIds) {
                    $q2->whereIn('id_prodi', $allowedProdiIds);
                });
            })
            ->with(['mahasiswa.prodi'])
            ->get()
            ->sortBy(fn (WisudaMahasiswa $p) => $p->mahasiswa?->nama ?? '')
            ->values();
    }

    /**
     * Cetak daftar peserta wisuda (PDF) — dipakai untuk logistik hari-H (name tag, urutan panggil, dll).
     */
    public function exportPesertaPdf(Request $request, Wisuda $wisuda): StreamedResponse
    {
        $user = $request->user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;
        $peserta = $this->pesertaWisudaRowsForExport($wisuda, $allowedProdiIds);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        .title { text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 10pt; margin-bottom: 12px; }
        table.peserta { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.peserta th { background-color: #334155; color: #fff; padding: 6px 4px; border: 1px solid #000; font-size: 8pt; text-align: center; }
        table.peserta td { padding: 4px; border: 1px solid #000; font-size: 8pt; vertical-align: top; }
        table.peserta td.num { text-align: center; width: 30px; }
        .footer { margin-top: 14px; font-size: 8pt; text-align: right; color: #444; }
        </style></head><body>';

        $html .= '<div class="title">DAFTAR PESERTA WISUDA</div>';
        $html .= '<div class="subtitle">'.htmlspecialchars($wisuda->nama).' — '
            .htmlspecialchars($wisuda->tanggal_wisuda?->format('d/m/Y') ?? '—').'</div>';

        $html .= '<table class="peserta"><thead><tr>
            <th style="width:30px">No</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Program Studi</th>
            <th>No. SK Wisuda</th>
        </tr></thead><tbody>';

        if ($peserta->isEmpty()) {
            $html .= '<tr><td colspan="5" style="text-align:center">Belum ada peserta terdaftar.</td></tr>';
        } else {
            $no = 1;
            foreach ($peserta as $p) {
                $m = $p->mahasiswa;
                $prodiText = $m?->prodi ? ($m->prodi->nama.($m->prodi->kode ? ' ('.$m->prodi->kode.')' : '')) : '—';
                $html .= '<tr>'
                    .'<td class="num">'.$no.'</td>'
                    .'<td>'.htmlspecialchars($m?->nim ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($m?->nama ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($prodiText).'</td>'
                    .'<td>'.htmlspecialchars($p->no_sk_wisuda ?? '—').'</td>'
                    .'</tr>';
                $no++;
            }
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Dicetak: '.now()->format('d/m/Y H:i').'</div></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'peserta_wisuda_'.$wisuda->id.'_'.date('Y-m-d_His').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Unduh daftar peserta wisuda (Excel).
     */
    public function exportPesertaExcel(Request $request, Wisuda $wisuda): StreamedResponse
    {
        $user = $request->user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;
        $peserta = $this->pesertaWisudaRowsForExport($wisuda, $allowedProdiIds);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Peserta Wisuda');

        $headers = ['No', 'NIM', 'Nama', 'Program Studi', 'No. SK Wisuda'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        $no = 1;
        foreach ($peserta as $p) {
            $m = $p->mahasiswa;
            $prodiText = $m?->prodi ? ($m->prodi->nama.($m->prodi->kode ? ' ('.$m->prodi->kode.')' : '')) : '—';
            $sheet->fromArray([[
                $no,
                $m?->nim ?? '—',
                $m?->nama ?? '—',
                $prodiText,
                $p->no_sk_wisuda ?? '—',
            ]], null, 'A'.$rowNum);
            $rowNum++;
            $no++;
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'peserta_wisuda_'.$wisuda->id.'_'.date('Y-m-d_His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function publicFileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
