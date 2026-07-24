<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\DosenWaliBimbingan;
use App\Models\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DosenWaliBimbinganController extends Controller
{
    /**
     * Pastikan dosen_wali (lewat mahasiswa terkait) berada dalam scope prodi user.
     */
    private function assertDosenWaliInUserScope(Request $request, DosenWali $dosenWali): void
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            $mahasiswa = $dosenWali->mahasiswa ?? $dosenWali->loadMissing('mahasiswa')->mahasiswa;
            if ($allowedProdiIds !== null && (! $mahasiswa || ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data bimbingan ini.');
            }
        }
    }

    public function index(Request $request, DosenWali $dosenWali): JsonResponse
    {
        $this->assertDosenWaliInUserScope($request, $dosenWali);

        $perPage = (int) $request->get('per_page', 20);
        $query = $dosenWali->bimbingan()->with('semester');

        if ($request->filled('id_semester')) {
            $query->where('id_semester', (int) $request->get('id_semester'));
        }

        $data = $query
            ->orderByDesc('tanggal_bimbingan')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request, DosenWali $dosenWali): JsonResponse
    {
        $this->assertDosenWaliInUserScope($request, $dosenWali);

        $validated = $request->validate([
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'catatan_dosen' => ['nullable', 'string'],
            'catatan_mhs' => ['nullable', 'string'],
            'tanggal_bimbingan' => ['nullable', 'date'],
            'waktu_validasi_dosen' => ['nullable', 'date'],
            'waktu_validasi_mhs' => ['nullable', 'date'],
            'langsung_validasi_dosen' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $waktuValidasiDosen = $request->boolean('langsung_validasi_dosen')
            ? now()
            : ($validated['waktu_validasi_dosen'] ?? null);

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('dosen_wali_bimbingan', 'public');
        }

        $row = $dosenWali->bimbingan()->create([
            'id_semester' => $validated['id_semester'],
            'catatan_dosen' => $validated['catatan_dosen'] ?? null,
            'catatan_mhs' => $validated['catatan_mhs'] ?? null,
            'tanggal_bimbingan' => $validated['tanggal_bimbingan'] ?? null,
            'waktu_validasi_dosen' => $waktuValidasiDosen,
            'waktu_validasi_mhs' => $validated['waktu_validasi_mhs'] ?? null,
            'file' => $path,
        ]);

        $row->load('semester');

        return response()->json($row, 201);
    }

    /**
     * Simpan catatan bimbingan untuk mahasiswa bimbingan dosen yang sedang login (portal dosen).
     */
    public function storeForBimbinganAkademikWali(Request $request, int $idMahasiswa): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Mahasiswa bukan bimbingan Anda atau tidak aktif.',
            ], 404);
        }

        return $this->store($request, $dosenWali);
    }

    /**
     * Mahasiswa: tambah entri catatan bimbingan (catatan mahasiswa) untuk dosen wali aktif.
     */
    public function storeForBimbinganAkademikMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::where('id_mahasiswa', $mahasiswa->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Anda tidak memiliki dosen wali aktif.',
            ], 404);
        }

        $validated = $request->validate([
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'catatan_mhs' => ['required', 'string'],
            'tanggal_bimbingan' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        if (trim($validated['catatan_mhs']) === '') {
            throw ValidationException::withMessages([
                'catatan_mhs' => ['Catatan tidak boleh kosong.'],
            ]);
        }

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('dosen_wali_bimbingan', 'public');
        }

        $row = $dosenWali->bimbingan()->create([
            'id_semester' => $validated['id_semester'],
            'catatan_dosen' => null,
            'catatan_mhs' => $validated['catatan_mhs'],
            'tanggal_bimbingan' => $validated['tanggal_bimbingan'] ?? null,
            'waktu_validasi_dosen' => null,
            'waktu_validasi_mhs' => null,
            'file' => $path,
        ]);

        $row->load('semester');

        return response()->json($this->formatBimbinganRowForMahasiswaApi($row), 201);
    }

    /**
     * Perbarui catatan bimbingan (portal dosen) — hanya baris milik dosen wali & mahasiswa bimbingan.
     */
    public function updateForBimbinganAkademikWali(Request $request, int $idMahasiswa, int $bimbingan): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Mahasiswa bukan bimbingan Anda atau tidak aktif.',
            ], 404);
        }

        $row = DosenWaliBimbingan::where('id', $bimbingan)
            ->where('id_dosen_wali', $dosenWali->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $row) {
            return response()->json([
                'message' => 'Data bimbingan tidak ditemukan.',
            ], 404);
        }

        return $this->update($request, $row);
    }

    /**
     * Mahasiswa: isi / ubah catatan_mhs dan sahkan validasi (waktu_validasi_mhs).
     */
    public function updateForBimbinganAkademikMahasiswa(Request $request, int $bimbingan): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::where('id_mahasiswa', $mahasiswa->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Anda tidak memiliki dosen wali aktif.',
            ], 404);
        }

        $row = DosenWaliBimbingan::where('id', $bimbingan)
            ->where('id_dosen_wali', $dosenWali->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $row) {
            return response()->json([
                'message' => 'Data bimbingan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'catatan_mhs' => ['nullable', 'string'],
            'validasi_mahasiswa' => ['sometimes', 'boolean'],
        ]);

        $willValidate = $request->boolean('validasi_mahasiswa');

        $normCatatan = static fn (?string $v): string => trim((string) ($v ?? ''));

        if ($row->waktu_validasi_mhs) {
            if (array_key_exists('catatan_mhs', $validated)
                && $normCatatan($validated['catatan_mhs'] ?? null) !== $normCatatan($row->catatan_mhs)) {
                return response()->json([
                    'message' => 'Catatan tidak dapat diubah setelah Anda memvalidasi entri ini.',
                ], 422);
            }
        } else {
            if (array_key_exists('catatan_mhs', $validated)) {
                $row->catatan_mhs = $validated['catatan_mhs'];
            }
            if ($willValidate) {
                if ($normCatatan($row->catatan_mhs) === '') {
                    return response()->json([
                        'message' => 'Isi catatan mahasiswa terlebih dahulu sebelum memvalidasi.',
                    ], 422);
                }
                $row->waktu_validasi_mhs = now();
            }
        }

        $row->save();
        $row->load('semester');

        return response()->json($this->formatBimbinganRowForMahasiswaApi($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBimbinganRowForMahasiswaApi(DosenWaliBimbingan $b): array
    {
        return [
            'id' => $b->id,
            'id_semester' => $b->id_semester,
            'semester' => $b->semester ? [
                'id' => $b->semester->id,
                'kode' => $b->semester->kode,
                'nama' => $b->semester->nama,
            ] : null,
            'tanggal_bimbingan' => $b->tanggal_bimbingan ? $b->tanggal_bimbingan->format('Y-m-d') : null,
            'catatan_dosen' => $b->catatan_dosen,
            'catatan_mhs' => $b->catatan_mhs,
            'file' => $b->file,
            'file_url' => $b->file_url,
            'waktu_validasi_dosen' => $b->waktu_validasi_dosen?->toIso8601String(),
            'waktu_validasi_mhs' => $b->waktu_validasi_mhs?->toIso8601String(),
        ];
    }

    /**
     * Ekspor riwayat bimbingan akademik ke Excel (dosen wali, satu mahasiswa bimbingan).
     */
    public function exportExcelForBimbinganAkademikWali(Request $request, int $idMahasiswa): JsonResponse|StreamedResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::with('mahasiswa')
            ->where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Mahasiswa bukan bimbingan Anda atau tidak aktif.',
            ], 404);
        }

        $query = $dosenWali->bimbingan()
            ->with('semester')
            ->orderByDesc('tanggal_bimbingan')
            ->orderByDesc('id');

        if ($request->filled('id_semester')) {
            $query->where('id_semester', (int) $request->get('id_semester'));
        }

        $bimbinganRows = $query->get();

        $mahasiswa = $dosenWali->mahasiswa;
        $nim = $mahasiswa->nim ?? 'mahasiswa';
        $nama = $mahasiswa->nama ?? '';

        $namaDosenLengkap = trim(
            ($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').
            ($dosen->nama ?? '').
            ($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : '')
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bimbingan');

        $sheet->setCellValue('A1', 'Bimbingan akademik');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Mahasiswa: '.$nama.' (NIM: '.$nim.')');
        $sheet->mergeCells('A2:H2');

        $sheet->setCellValue('A3', 'Dosen wali: '.($namaDosenLengkap !== '' ? $namaDosenLengkap : '-'));
        $sheet->mergeCells('A3:H3');
        $sheet->setCellValue('A4', 'Diekspor: '.now()->format('Y-m-d H:i:s'));
        $sheet->mergeCells('A4:H4');

        $headers = [
            'No.',
            'Semester',
            'Tanggal bimbingan',
            'Catatan dosen',
            'Catatan mahasiswa',
            'Waktu validasi dosen',
            'Waktu validasi mahasiswa',
            'Ada berkas',
        ];
        $headerRow = 6;
        $sheet->fromArray([$headers], null, 'A'.$headerRow);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A'.$headerRow.':H'.$headerRow)->applyFromArray($headerStyle);

        $dataStart = $headerRow + 1;
        $no = 1;
        $rowIndex = $dataStart;
        foreach ($bimbinganRows as $r) {
            $sem = $r->semester;
            $semLabel = $sem ? trim(($sem->kode ?? '').' '.($sem->nama ?? '')) : '-';
            $sheet->fromArray([[
                $no++,
                $semLabel,
                $r->tanggal_bimbingan ? $r->tanggal_bimbingan->format('Y-m-d') : '',
                $r->catatan_dosen ?? '',
                $r->catatan_mhs ?? '',
                $r->waktu_validasi_dosen ? $r->waktu_validasi_dosen->format('Y-m-d H:i:s') : '',
                $r->waktu_validasi_mhs ? $r->waktu_validasi_mhs->format('Y-m-d H:i:s') : '',
                $r->file ? 'Ya' : 'Tidak',
            ]], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(12);

        if ($rowIndex > $dataStart) {
            $last = $rowIndex - 1;
            $sheet->getStyle('D'.$dataStart.':E'.$last)->getAlignment()->setWrapText(true)->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
            );
        }

        $safeNim = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $nim) ?: 'mahasiswa';
        $filename = 'bimbingan_akademik_'.$safeNim.'_'.date('Ymd_His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename),
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function update(Request $request, DosenWaliBimbingan $dosenWaliBimbingan): JsonResponse
    {
        $dosenWaliBimbingan->loadMissing('dosenWali.mahasiswa');
        $this->assertDosenWaliInUserScope($request, $dosenWaliBimbingan->dosenWali);

        $validated = $request->validate([
            'id_semester' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'catatan_dosen' => ['nullable', 'string'],
            'catatan_mhs' => ['nullable', 'string'],
            'tanggal_bimbingan' => ['nullable', 'date'],
            'waktu_validasi_dosen' => ['nullable', 'date'],
            'waktu_validasi_mhs' => ['nullable', 'date'],
            'langsung_validasi_dosen' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'hapus_file' => ['nullable', 'boolean'],
        ]);

        $hapusFile = $request->boolean('hapus_file');

        if ($hapusFile && $dosenWaliBimbingan->file) {
            Storage::disk('public')->delete($dosenWaliBimbingan->file);
            $dosenWaliBimbingan->file = null;
        }

        if ($request->hasFile('file')) {
            if ($dosenWaliBimbingan->file) {
                Storage::disk('public')->delete($dosenWaliBimbingan->file);
            }
            $dosenWaliBimbingan->file = $request->file('file')->store('dosen_wali_bimbingan', 'public');
        }

        $fillData = Arr::only($validated, [
            'id_semester',
            'catatan_dosen',
            'catatan_mhs',
            'tanggal_bimbingan',
            'waktu_validasi_dosen',
            'waktu_validasi_mhs',
        ]);

        if ($request->boolean('langsung_validasi_dosen')) {
            unset($fillData['waktu_validasi_dosen']);
        }

        $dosenWaliBimbingan->fill($fillData);

        if ($request->boolean('langsung_validasi_dosen')) {
            $dosenWaliBimbingan->waktu_validasi_dosen = now();
        }

        $dosenWaliBimbingan->save();

        return response()->json($dosenWaliBimbingan->fresh(['semester']));
    }

    public function destroy(Request $request, DosenWaliBimbingan $dosenWaliBimbingan): JsonResponse
    {
        $dosenWaliBimbingan->loadMissing('dosenWali.mahasiswa');
        $this->assertDosenWaliInUserScope($request, $dosenWaliBimbingan->dosenWali);

        $dosenWaliBimbingan->delete();

        return response()->json(['message' => 'Data bimbingan dihapus']);
    }
}
