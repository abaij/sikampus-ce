<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\KelasDosen;
use App\Models\MateriPerkuliahan;
use App\Models\Perkuliahan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MateriPerkuliahanController extends Controller
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
     * @param  Collection<int, MateriPerkuliahan>  $collection
     */
    private function mapMateriRows(Collection $collection)
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $collection->map(function (MateriPerkuliahan $item) use ($baseUrl) {
            $path = ltrim((string) $item->file, '/');

            return [
                'id' => $item->id,
                'id_jadwal' => $item->id_jadwal,
                'nama' => $item->nama,
                'file' => $item->file,
                'url' => $baseUrl.'/storage/'.$path,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        })->values();
    }

    /**
     * Daftar materi berdasarkan slot jadwal (tabel materi_perkuliahan.id_jadwal).
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

        $materi = MateriPerkuliahan::where('id_jadwal', $jadwalId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $this->mapMateriRows($materi)]);
    }

    /**
     * Daftar materi untuk jadwal yang sama dengan perkuliahan (kompatibilitas route lama).
     */
    public function getByPerkuliahan(Request $request, int $perkuliahanId): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $perkuliahan = Perkuliahan::with('jadwal.kelas')->find($perkuliahanId);
        if (! $perkuliahan || ! $perkuliahan->jadwal) {
            return response()->json(['message' => 'Perkuliahan tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $perkuliahan->jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke perkuliahan ini'], 403);
        }

        $materi = MateriPerkuliahan::where('id_jadwal', $perkuliahan->id_jadwal)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        // Respons array langsung (kompatibel klien lama)
        return response()->json($this->mapMateriRows($materi));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal,id'],
            'id_perkuliahan' => ['nullable', 'integer', 'exists:perkuliahan,id'],
            'nama' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        if (empty($validated['id_jadwal']) && empty($validated['id_perkuliahan'])) {
            return response()->json(['message' => 'id_jadwal atau id_perkuliahan wajib diisi'], 422);
        }

        if (! empty($validated['id_perkuliahan'])) {
            $perkuliahan = Perkuliahan::with('jadwal.kelas')->find($validated['id_perkuliahan']);
            if (! $perkuliahan || ! $perkuliahan->jadwal) {
                return response()->json(['message' => 'Perkuliahan tidak ditemukan'], 404);
            }
            $jadwal = $perkuliahan->jadwal;
        } else {
            $jadwal = Jadwal::with('kelas')->find($validated['id_jadwal']);
        }

        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses'], 403);
        }

        $file = $request->file('file');
        $filename = time().'_'.uniqid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('materi_perkuliahan', $filename, 'public');

        $baseUrl = rtrim((string) config('app.url'), '/');
        $url = $baseUrl.'/storage/'.ltrim($path, '/');

        $materi = MateriPerkuliahan::create([
            'id_jadwal' => $jadwal->id,
            'nama' => $validated['nama'] ?? $file->getClientOriginalName(),
            'file' => $path,
        ]);

        return response()->json([
            'id' => $materi->id,
            'id_jadwal' => $materi->id_jadwal,
            'nama' => $materi->nama,
            'file' => $materi->file,
            'url' => $url,
            'created_at' => $materi->created_at?->toIso8601String(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $materi = MateriPerkuliahan::find($id);
        if (! $materi) {
            return response()->json(['message' => 'Materi perkuliahan tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::with('kelas')->find($materi->id_jadwal);
        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke materi ini'], 403);
        }

        $validated = $request->validate([
            'nama' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file' => ['sometimes', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('file')) {
            if ($materi->file && Storage::disk('public')->exists($materi->file)) {
                Storage::disk('public')->delete($materi->file);
            }

            $file = $request->file('file');
            $filename = time().'_'.uniqid().'_'.$file->getClientOriginalName();
            $path = $file->storeAs('materi_perkuliahan', $filename, 'public');
            $validated['file'] = $path;
        }

        $materi->update($validated);

        $baseUrl = rtrim((string) config('app.url'), '/');
        $url = $baseUrl.'/storage/'.ltrim((string) $materi->file, '/');

        return response()->json([
            'id' => $materi->id,
            'id_jadwal' => $materi->id_jadwal,
            'nama' => $materi->nama,
            'file' => $materi->file,
            'url' => $url,
            'created_at' => $materi->created_at?->toIso8601String(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = request()->user();
        $dosen = Dosen::where('id_user', $user->id)->first();
        if (! $dosen) {
            return response()->json(['message' => 'Data dosen tidak ditemukan'], 404);
        }

        $materi = MateriPerkuliahan::find($id);
        if (! $materi) {
            return response()->json(['message' => 'Materi perkuliahan tidak ditemukan'], 404);
        }

        $jadwal = Jadwal::with('kelas')->find($materi->id_jadwal);
        if (! $jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if (! $this->dosenCanAccessJadwal($dosen, $jadwal)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke materi ini'], 403);
        }

        if ($materi->file && Storage::disk('public')->exists($materi->file)) {
            Storage::disk('public')->delete($materi->file);
        }

        $materi->delete();

        return response()->json(['message' => 'Materi perkuliahan berhasil dihapus']);
    }
}
