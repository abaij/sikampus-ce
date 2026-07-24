<?php

namespace App\Http\Controllers;

use App\Models\Pengumuman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengumumanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $audien = $request->get('audien');
        $prioritas = $request->get('prioritas');
        $status = $request->get('status'); // aktif, akan_datang, selesai

        $query = Pengumuman::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('isi', 'like', "%{$search}%");
            });
        }

        if ($audien) {
            $query->where('audien', $audien);
        }

        if ($prioritas) {
            $query->where('prioritas', $prioritas);
        }

        // Filter berdasarkan status (aktif, akan_datang, selesai)
        $now = now();
        if ($status === 'aktif') {
            $query->where(function ($q) use ($now) {
                $q->whereNull('tanggal_mulai')
                  ->orWhere('tanggal_mulai', '<=', $now);
            })->where(function ($q) use ($now) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $now);
            });
        } elseif ($status === 'akan_datang') {
            $query->where('tanggal_mulai', '>', $now);
        } elseif ($status === 'selesai') {
            $query->whereNotNull('tanggal_selesai')
                  ->where('tanggal_selesai', '<', $now);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string'],
            'audien' => ['nullable', 'string', Rule::in(['mahasiswa', 'dosen', 'staff', 'alumni'])],
            'prioritas' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        // Set created_by dari user yang sedang login
        if ($request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $pengumuman = Pengumuman::create($validated);

        return response()->json($pengumuman, 201);
    }

    public function show(Pengumuman $pengumuman): JsonResponse
    {
        return response()->json($pengumuman);
    }

    public function update(Request $request, Pengumuman $pengumuman): JsonResponse
    {
        $validated = $request->validate([
            'judul' => ['sometimes', 'required', 'string', 'max:255'],
            'isi' => ['sometimes', 'required', 'string'],
            'audien' => ['nullable', 'string', Rule::in(['mahasiswa', 'dosen', 'staff', 'alumni'])],
            'prioritas' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        // Set updated_by dari user yang sedang login
        if ($request->user()) {
            $validated['updated_by'] = $request->user()->id;
        }

        $pengumuman->update($validated);

        return response()->json($pengumuman);
    }

    public function destroy(Request $request, Pengumuman $pengumuman): JsonResponse
    {
        // Set deleted_by dari user yang sedang login
        if ($request->user()) {
            $pengumuman->deleted_by = $request->user()->id;
            $pengumuman->save();
        }

        $pengumuman->delete();

        return response()->json(['message' => 'Pengumuman dihapus']);
    }

    /**
     * Get public pengumuman (aktif) - tanpa autentikasi
     */
    public function getPublicPengumuman(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $audien = $request->get('audien');
        $prioritas = $request->get('prioritas');

        $now = now();
        $query = Pengumuman::query()
            ->where(function ($q) use ($now) {
                $q->whereNull('tanggal_mulai')
                  ->orWhere('tanggal_mulai', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $now);
            });

        if ($audien) {
            $query->where('audien', $audien);
        }

        if ($prioritas) {
            $query->where('prioritas', $prioritas);
        }

        $data = $query->orderBy('prioritas', 'desc')
                     ->orderBy('created_at', 'desc')
                     ->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Get pengumuman aktif untuk mahasiswa yang sedang login
     */
    public function getAktifForMahasiswa(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 5);
        $prioritas = $request->get('prioritas');

        $now = now();
        $query = Pengumuman::query()
            ->where(function ($q) {
                $q->where('audien', 'mahasiswa')
                  ->orWhereNull('audien'); // Jika audien null, berarti untuk semua
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('tanggal_mulai')
                  ->orWhere('tanggal_mulai', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $now);
            });

        if ($prioritas) {
            $query->where('prioritas', $prioritas);
        }

        $data = $query->orderBy('prioritas', 'desc')
                     ->orderBy('created_at', 'desc')
                     ->paginate($perPage);

        return response()->json($data);
    }
}

