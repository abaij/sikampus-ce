<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\Mahasiswa;
use App\Models\Krs;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseDetail;
use App\Models\SurveyQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $isActive = $request->get('is_active');

        $query = Survey::with(['semester']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['required', 'string', 'max:255', 'unique:survey,kode'],
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $survey = Survey::create($validated);

        $survey->load('semester');

        return response()->json($survey, 201);
    }

    public function show(Survey $survey): JsonResponse
    {
        $survey->load('semester');

        return response()->json([
            'success' => true,
            'data' => $survey,
        ]);
    }

    public function update(Request $request, Survey $survey): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('survey', 'kode')->ignore($survey->id),
            ],
            'id_semester' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $survey->update($validated);
        $survey->load('semester');

        return response()->json($survey);
    }

    public function destroy(Survey $survey): JsonResponse
    {
        $survey->delete();

        return response()->json(['message' => 'Survey dihapus']);
    }

    /**
     * Get survey aktif untuk mahasiswa dengan mata kuliah yang dikontrak
     */
    public function getSurveyAktifForMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Ambil data mahasiswa dari user
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();
        
        if (!$mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        // Ambil semua survey yang aktif
        $surveys = Survey::with(['semester'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Ambil semua KRS yang sudah disetujui untuk mahasiswa ini
        $krsList = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
            'kelas.prodi',
        ])
        ->where('id_mahasiswa', $mahasiswa->id)
        ->whereNotNull('approved_at') // Hanya KRS yang sudah disetujui
        ->whereNull('deleted_at')
        ->get();

        // Ambil survey response yang sudah ada untuk mahasiswa ini
        $existingResponses = DB::table('survey_response')
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('id_survey')
            ->map(function ($responses) {
                return $responses->pluck('id_krs')->filter()->toArray();
            })
            ->toArray();

        // Format data survey dengan mata kuliah
        $result = [];
        foreach ($surveys as $survey) {
            // Filter KRS berdasarkan semester survey
            $krsForSurvey = $krsList->filter(function ($krs) use ($survey) {
                $semesterKrs = $krs->kelas->semester;
                return $semesterKrs && $semesterKrs->id === $survey->id_semester;
            });

            if ($krsForSurvey->isEmpty()) {
                continue; // Skip survey jika tidak ada mata kuliah yang dikontrak
            }

            $mataKuliah = $krsForSurvey->map(function ($krs) use ($survey, $existingResponses) {
                $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
                $kelas = $krs->kelas;
                
                if (!$matkul) {
                    return null;
                }

                // Cek apakah sudah mengisi survey untuk mata kuliah ini
                $sudahDiisi = isset($existingResponses[$survey->id]) && 
                             in_array($krs->id, $existingResponses[$survey->id]);

                return [
                    'id_krs' => $krs->id,
                    'kode_matkul' => $matkul->kode ?? '-',
                    'nama_matkul' => $matkul->nama ?? '-',
                    'sks' => $matkul->sks ?? 0,
                    'nama_kelas' => $kelas->nama ?? '-',
                    'prodi' => $kelas->prodi ? [
                        'id' => $kelas->prodi->id,
                        'nama' => $kelas->prodi->nama,
                    ] : null,
                    'sudah_diisi' => $sudahDiisi,
                ];
            })->filter()->values();

            if ($mataKuliah->isEmpty()) {
                continue;
            }

            $result[] = [
                'id' => $survey->id,
                'nama' => $survey->nama,
                'kode' => $survey->kode,
                'keterangan' => $survey->keterangan,
                'tanggal_mulai' => $survey->tanggal_mulai,
                'tanggal_selesai' => $survey->tanggal_selesai,
                'semester' => $survey->semester ? [
                    'id' => $survey->semester->id,
                    'kode' => $survey->semester->kode,
                    'nama' => $survey->semester->nama,
                ] : null,
                'mata_kuliah' => $mataKuliah,
            ];
        }

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get existing survey response untuk mahasiswa
     */
    public function getSurveyResponse(Request $request, int $surveyId, int $idKrs): JsonResponse
    {
        $user = $request->user();
        
        // Ambil data mahasiswa dari user
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();
        
        if (!$mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        // Ambil survey response
        $response = DB::table('survey_response')
            ->where('id_survey', $surveyId)
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_krs', $idKrs)
            ->whereNull('deleted_at')
            ->first();

        if (!$response) {
            return response()->json([
                'data' => null
            ]);
        }

        // Ambil response detail (jawaban pertanyaan)
        $responseDetails = DB::table('survey_response_detail')
            ->where('id_survey_response', $response->id)
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($detail) {
                return [
                    'id_survey_question' => $detail->id_survey_question,
                    'nilai_numerik' => $detail->nilai_numerik,
                    'nilai_text' => $detail->nilai_text,
                ];
            })
            ->keyBy('id_survey_question')
            ->toArray();

        return response()->json([
            'data' => [
                'id' => $response->id,
                'id_survey' => $response->id_survey,
                'id_krs' => $response->id_krs,
                'feedback' => $response->feedback,
                'tanggal_submit' => $response->tanggal_submit,
                'responses' => $responseDetails,
            ]
        ]);
    }

    /**
     * Submit survey response untuk mahasiswa
     */
    public function submitSurveyResponse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_survey' => ['required', 'integer', 'exists:survey,id'],
            'id_krs' => ['required', 'integer', 'exists:krs,id'],
            'responses' => ['required', 'array'],
            'responses.*.id_survey_question' => ['required', 'integer', 'exists:survey_question,id'],
            'responses.*.nilai_numerik' => ['nullable', 'integer'],
            'responses.*.nilai_text' => ['nullable', 'string'],
            'feedback' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        
        // Ambil data mahasiswa dari user
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();
        
        if (!$mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        // Verifikasi bahwa KRS milik mahasiswa ini
        $krs = Krs::find($validated['id_krs']);
        if (!$krs || $krs->id_mahasiswa !== $mahasiswa->id) {
            return response()->json([
                'message' => 'KRS tidak ditemukan atau bukan milik Anda'
            ], 404);
        }

        // Verifikasi bahwa survey aktif
        $survey = Survey::find($validated['id_survey']);
        if (!$survey || !$survey->is_active) {
            return response()->json([
                'message' => 'Survey tidak aktif'
            ], 400);
        }

        // Cek apakah sudah pernah mengisi survey untuk KRS ini
        // Unique constraint: id_survey + id_mahasiswa + id_krs
        // Jadi satu mahasiswa bisa mengisi survey yang sama untuk KRS yang berbeda
        $existingResponse = DB::table('survey_response')
            ->where('id_survey', $validated['id_survey'])
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_krs', $validated['id_krs'])
            ->whereNull('deleted_at')
            ->first();

        try {
            DB::beginTransaction();

            if ($existingResponse) {
                // Update existing response
                DB::table('survey_response')
                    ->where('id', $existingResponse->id)
                    ->update([
                        'tanggal_submit' => now(),
                        'feedback' => $validated['feedback'] ?? null,
                        'updated_at' => now(),
                    ]);
                $responseId = $existingResponse->id;
            } else {
                // Create new response
                // Unique constraint sudah include id_krs, jadi tidak perlu handle conflict
                $responseId = DB::table('survey_response')->insertGetId([
                    'id_survey' => $validated['id_survey'],
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_krs' => $validated['id_krs'],
                    'tanggal_submit' => now(),
                    'feedback' => $validated['feedback'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Hapus response detail yang lama
            DB::table('survey_response_detail')
                ->where('id_survey_response', $responseId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            // Insert response detail baru
            foreach ($validated['responses'] as $response) {
                DB::table('survey_response_detail')->insert([
                    'id_survey_response' => $responseId,
                    'id_survey_question' => $response['id_survey_question'],
                    'nilai_numerik' => $response['nilai_numerik'] ?? null,
                    'nilai_text' => $response['nilai_text'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Survey berhasil disubmit',
                'id' => $responseId,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan survey: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistik pengisian survey
     */
    public function getStatistik(Request $request, Survey $survey): JsonResponse
    {
        $prodiId = $request->get('id_prodi');
        $sortBy = $request->get('sort_by', 'nilai'); // 'nilai' or 'pertanyaan'
        $sortOrder = $request->get('sort_order', 'desc'); // 'asc' or 'desc'

        // Hitung jumlah mahasiswa yang sudah mengisi survey
        $queryResponse = SurveyResponse::where('id_survey', $survey->id)
            ->whereNull('survey_response.deleted_at');

        if ($prodiId) {
            $queryResponse->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                ->where('mahasiswa.id_prodi', $prodiId)
                ->whereNull('mahasiswa.deleted_at');
        }

        $totalResponden = $queryResponse->distinct('survey_response.id_mahasiswa')->count('survey_response.id_mahasiswa');

        // Ambil semua pertanyaan survey dengan options
        $questions = SurveyQuestion::where('id_survey', $survey->id)
            ->whereNull('deleted_at')
            ->with(['options' => function ($q) {
                $q->whereNull('deleted_at')->orderBy('urutan');
            }])
            ->orderBy('id')
            ->get();

        // Hitung statistik per pertanyaan
        $statistikPertanyaan = [];
        foreach ($questions as $question) {
            // Ambil semua response detail untuk pertanyaan ini
            $queryDetail = SurveyResponseDetail::where('survey_response_detail.id_survey_question', $question->id)
                ->whereNull('survey_response_detail.deleted_at')
                ->join('survey_response', 'survey_response_detail.id_survey_response', '=', 'survey_response.id')
                ->where('survey_response.id_survey', $survey->id)
                ->whereNull('survey_response.deleted_at');

            if ($prodiId) {
                $queryDetail->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                    ->where('mahasiswa.id_prodi', $prodiId)
                    ->whereNull('mahasiswa.deleted_at');
            }

            $totalJawaban = $queryDetail->count();
            
            // Hitung rata-rata nilai numerik (jika ada)
            $avgNilai = null;
            if ($question->tipe !== 'essay') {
                $avgNilai = $queryDetail->avg('survey_response_detail.nilai_numerik');
                $avgNilai = $avgNilai ? round((float)$avgNilai, 2) : null;
            }

            // Hitung distribusi jawaban untuk pertanyaan dengan opsi
            $distribusiJawaban = [];
            if ($question->tipe !== 'essay' && $question->options) {
                foreach ($question->options as $option) {
                    $countQuery = SurveyResponseDetail::where('survey_response_detail.id_survey_question', $question->id)
                        ->where('survey_response_detail.nilai_numerik', $option->nilai_numerik)
                        ->whereNull('survey_response_detail.deleted_at')
                        ->join('survey_response', 'survey_response_detail.id_survey_response', '=', 'survey_response.id')
                        ->where('survey_response.id_survey', $survey->id)
                        ->whereNull('survey_response.deleted_at');

                    if ($prodiId) {
                        $countQuery->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                            ->where('mahasiswa.id_prodi', $prodiId)
                            ->whereNull('mahasiswa.deleted_at');
                    }

                    $count = $countQuery->count();
                    
                    $distribusiJawaban[] = [
                        'opsi' => $option->opsi,
                        'nilai_numerik' => $option->nilai_numerik,
                        'jumlah' => $count,
                        'persentase' => $totalJawaban > 0 ? round(($count / $totalJawaban) * 100, 2) : 0,
                    ];
                }
            }

            $statistikPertanyaan[] = [
                'id' => $question->id,
                'pertanyaan' => $question->pertanyaan,
                'tipe' => $question->tipe,
                'total_jawaban' => $totalJawaban,
                'rata_rata_nilai' => $avgNilai,
                'distribusi_jawaban' => $distribusiJawaban,
            ];
        }

        // Sorting berdasarkan nilai atau pertanyaan
        if ($sortBy === 'nilai') {
            usort($statistikPertanyaan, function ($a, $b) use ($sortOrder) {
                $nilaiA = $a['rata_rata_nilai'] ?? 0;
                $nilaiB = $b['rata_rata_nilai'] ?? 0;
                return $sortOrder === 'desc' ? $nilaiB <=> $nilaiA : $nilaiA <=> $nilaiB;
            });
        } else {
            usort($statistikPertanyaan, function ($a, $b) use ($sortOrder) {
                return $sortOrder === 'desc' 
                    ? strcmp($b['pertanyaan'], $a['pertanyaan'])
                    : strcmp($a['pertanyaan'], $b['pertanyaan']);
            });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_responden' => $totalResponden,
                'pertanyaan' => $statistikPertanyaan,
            ],
        ]);
    }

    /**
     * Export statistik survey ke Excel
     */
    public function exportStatistik(Request $request, Survey $survey): StreamedResponse
    {
        $prodiId = $request->get('id_prodi');
        $sortBy = $request->get('sort_by', 'nilai');
        $sortOrder = $request->get('sort_order', 'desc');

        // Ambil data statistik (menggunakan logic yang sama dengan getStatistik)
        $queryResponse = SurveyResponse::where('id_survey', $survey->id)
            ->whereNull('survey_response.deleted_at');

        if ($prodiId) {
            $queryResponse->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                ->where('mahasiswa.id_prodi', $prodiId)
                ->whereNull('mahasiswa.deleted_at');
        }

        $totalResponden = $queryResponse->distinct('survey_response.id_mahasiswa')->count('survey_response.id_mahasiswa');

        // Ambil semua pertanyaan survey dengan options
        $questions = SurveyQuestion::where('id_survey', $survey->id)
            ->whereNull('deleted_at')
            ->with(['options' => function ($q) {
                $q->whereNull('deleted_at')->orderBy('urutan');
            }])
            ->orderBy('id')
            ->get();

        // Hitung statistik per pertanyaan
        $statistikPertanyaan = [];
        foreach ($questions as $question) {
            $queryDetail = SurveyResponseDetail::where('survey_response_detail.id_survey_question', $question->id)
                ->whereNull('survey_response_detail.deleted_at')
                ->join('survey_response', 'survey_response_detail.id_survey_response', '=', 'survey_response.id')
                ->where('survey_response.id_survey', $survey->id)
                ->whereNull('survey_response.deleted_at');

            if ($prodiId) {
                $queryDetail->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                    ->where('mahasiswa.id_prodi', $prodiId)
                    ->whereNull('mahasiswa.deleted_at');
            }

            $totalJawaban = $queryDetail->count();
            
            $avgNilai = null;
            if ($question->tipe !== 'essay') {
                $avgNilai = $queryDetail->avg('survey_response_detail.nilai_numerik');
                $avgNilai = $avgNilai ? round((float)$avgNilai, 2) : null;
            }

            $distribusiJawaban = [];
            if ($question->tipe !== 'essay' && $question->options) {
                foreach ($question->options as $option) {
                    $countQuery = SurveyResponseDetail::where('survey_response_detail.id_survey_question', $question->id)
                        ->where('survey_response_detail.nilai_numerik', $option->nilai_numerik)
                        ->whereNull('survey_response_detail.deleted_at')
                        ->join('survey_response', 'survey_response_detail.id_survey_response', '=', 'survey_response.id')
                        ->where('survey_response.id_survey', $survey->id)
                        ->whereNull('survey_response.deleted_at');

                    if ($prodiId) {
                        $countQuery->join('mahasiswa', 'survey_response.id_mahasiswa', '=', 'mahasiswa.id')
                            ->where('mahasiswa.id_prodi', $prodiId)
                            ->whereNull('mahasiswa.deleted_at');
                    }

                    $count = $countQuery->count();
                    
                    $distribusiJawaban[] = [
                        'opsi' => $option->opsi,
                        'nilai_numerik' => $option->nilai_numerik,
                        'jumlah' => $count,
                        'persentase' => $totalJawaban > 0 ? round(($count / $totalJawaban) * 100, 2) : 0,
                    ];
                }
            }

            $statistikPertanyaan[] = [
                'id' => $question->id,
                'pertanyaan' => $question->pertanyaan,
                'tipe' => $question->tipe,
                'total_jawaban' => $totalJawaban,
                'rata_rata_nilai' => $avgNilai,
                'distribusi_jawaban' => $distribusiJawaban,
            ];
        }

        // Sorting
        if ($sortBy === 'nilai') {
            usort($statistikPertanyaan, function ($a, $b) use ($sortOrder) {
                $nilaiA = $a['rata_rata_nilai'] ?? 0;
                $nilaiB = $b['rata_rata_nilai'] ?? 0;
                return $sortOrder === 'desc' ? $nilaiB <=> $nilaiA : $nilaiA <=> $nilaiB;
            });
        } else {
            usort($statistikPertanyaan, function ($a, $b) use ($sortOrder) {
                return $sortOrder === 'desc' 
                    ? strcmp($b['pertanyaan'], $a['pertanyaan'])
                    : strcmp($a['pertanyaan'], $b['pertanyaan']);
            });
        }

        // Buat spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Statistik Survey');

        // Header informasi survey
        $row = 1;
        $sheet->setCellValue('A' . $row, 'STATISTIK SURVEY');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue('A' . $row, 'Nama Survey:');
        $sheet->setCellValue('B' . $row, $survey->nama);
        $row++;

        $sheet->setCellValue('A' . $row, 'Kode Survey:');
        $sheet->setCellValue('B' . $row, $survey->kode);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Responden:');
        $sheet->setCellValue('B' . $row, $totalResponden);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Pertanyaan:');
        $sheet->setCellValue('B' . $row, count($statistikPertanyaan));
        $row += 2;

        // Header tabel statistik
        $headers = ['No', 'Pertanyaan', 'Tipe', 'Total Jawaban', 'Rata-rata Nilai'];
        $sheet->fromArray([$headers], null, 'A' . $row);
        
        // Style header
        $headerRange = 'A' . $row . ':E' . $row;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);
        
        $row++;

        // Data statistik pertanyaan
        $no = 1;
        foreach ($statistikPertanyaan as $item) {
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, $item['pertanyaan']);
            $sheet->setCellValue('C' . $row, $item['tipe'] ?? 'essay');
            $sheet->setCellValue('D' . $row, $item['total_jawaban']);
            $sheet->setCellValue('E' . $row, $item['rata_rata_nilai'] ?? '-');
            
            // Wrap text untuk pertanyaan
            $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true);
            
            $row++;
            $no++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(18);

        // Buat sheet untuk distribusi jawaban
        if (count($statistikPertanyaan) > 0) {
            $distSheet = $spreadsheet->createSheet();
            $distSheet->setTitle('Distribusi Jawaban');
            $distRow = 1;

            $distSheet->setCellValue('A' . $distRow, 'DISTRIBUSI JAWABAN PER PERTANYAAN');
            $distSheet->mergeCells('A' . $distRow . ':D' . $distRow);
            $distSheet->getStyle('A' . $distRow)->getFont()->setBold(true)->setSize(14);
            $distRow += 2;

            foreach ($statistikPertanyaan as $item) {
                if (empty($item['distribusi_jawaban'])) {
                    continue;
                }

                $distSheet->setCellValue('A' . $distRow, 'Pertanyaan:');
                $distSheet->setCellValue('B' . $distRow, $item['pertanyaan']);
                $distSheet->mergeCells('B' . $distRow . ':D' . $distRow);
                $distSheet->getStyle('A' . $distRow . ':B' . $distRow)->getFont()->setBold(true);
                $distRow++;

                // Header distribusi
                $distHeaders = ['Opsi', 'Nilai Numerik', 'Jumlah', 'Persentase (%)'];
                $distSheet->fromArray([$distHeaders], null, 'A' . $distRow);
                $distSheet->getStyle('A' . $distRow . ':D' . $distRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E7E6E6'],
                    ],
                ]);
                $distRow++;

                // Data distribusi
                foreach ($item['distribusi_jawaban'] as $dist) {
                    $distSheet->setCellValue('A' . $distRow, $dist['opsi']);
                    $distSheet->setCellValue('B' . $distRow, $dist['nilai_numerik'] ?? '-');
                    $distSheet->setCellValue('C' . $distRow, $dist['jumlah']);
                    $distSheet->setCellValue('D' . $distRow, $dist['persentase']);
                    $distRow++;
                }

                $distRow += 2; // Spacing antar pertanyaan
            }

            // Set column widths untuk distribusi
            $distSheet->getColumnDimension('A')->setWidth(40);
            $distSheet->getColumnDimension('B')->setWidth(15);
            $distSheet->getColumnDimension('C')->setWidth(12);
            $distSheet->getColumnDimension('D')->setWidth(15);
        }

        // Generate filename
        $prodiText = $prodiId ? '_prodi_' . $prodiId : '';
        $filename = 'statistik_survey_' . $survey->kode . $prodiText . '_' . date('YmdHis') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}

