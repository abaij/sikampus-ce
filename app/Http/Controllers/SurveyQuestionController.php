<?php

namespace App\Http\Controllers;

use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $surveyId = $request->get('id_survey');

        if (!$surveyId) {
            return response()->json(['message' => 'id_survey diperlukan'], 400);
        }

        $questions = SurveyQuestion::with(['options'])
            ->where('id_survey', $surveyId)
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_survey' => ['required', 'integer', 'exists:survey,id'],
            'pertanyaan' => ['required', 'string'],
            'tipe' => ['nullable', 'string', 'in:likert,essay,multiple_choice,single_choice'],
            'options' => ['nullable', 'array'],
            'options.*.opsi' => ['required_with:options', 'string'],
            'options.*.nilai_numerik' => ['nullable', 'integer'],
            'options.*.urutan' => ['nullable', 'integer'],
        ]);

        try {
            DB::beginTransaction();

            $question = SurveyQuestion::create([
                'id_survey' => $validated['id_survey'],
                'pertanyaan' => $validated['pertanyaan'],
                'tipe' => $validated['tipe'] ?? null,
            ]);

            if (isset($validated['options']) && is_array($validated['options'])) {
                foreach ($validated['options'] as $index => $option) {
                    SurveyQuestionOption::create([
                        'id_survey_question' => $question->id,
                        'opsi' => $option['opsi'],
                        'nilai_numerik' => $option['nilai_numerik'] ?? null,
                        'urutan' => $option['urutan'] ?? $index,
                    ]);
                }
            }

            $question->load('options');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pertanyaan survey berhasil dibuat',
                'data' => $question,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'survey_question_option_unique')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opsi dengan kombinasi opsi dan nilai numerik yang sama sudah ada untuk pertanyaan ini.',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pertanyaan survey: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pertanyaan survey: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(SurveyQuestion $surveyQuestion): JsonResponse
    {
        $surveyQuestion->load('options');

        return response()->json([
            'success' => true,
            'data' => $surveyQuestion,
        ]);
    }

    public function update(Request $request, SurveyQuestion $surveyQuestion): JsonResponse
    {
        $validated = $request->validate([
            'pertanyaan' => ['sometimes', 'required', 'string'],
            'tipe' => ['nullable', 'string', 'in:likert,essay,multiple_choice,single_choice'],
            'options' => ['nullable', 'array'],
            'options.*.id' => ['nullable', 'integer', 'exists:survey_question_option,id'],
            'options.*.opsi' => ['required_with:options', 'string'],
            'options.*.nilai_numerik' => ['nullable', 'integer'],
            'options.*.urutan' => ['nullable', 'integer'],
        ]);

        try {
            DB::beginTransaction();

            $surveyQuestion->update([
                'pertanyaan' => $validated['pertanyaan'] ?? $surveyQuestion->pertanyaan,
                'tipe' => $validated['tipe'] ?? $surveyQuestion->tipe,
            ]);

            if (isset($validated['options'])) {
                // Hapus opsi yang tidak ada dalam request
                $optionIds = array_filter(array_column($validated['options'], 'id'));
                $surveyQuestion->options()->whereNotIn('id', $optionIds)->delete();

                // Update atau create opsi
                foreach ($validated['options'] as $index => $optionData) {
                    if (isset($optionData['id'])) {
                        // Update existing option
                        $option = SurveyQuestionOption::find($optionData['id']);
                        if ($option) {
                            $option->update([
                                'opsi' => $optionData['opsi'],
                                'nilai_numerik' => $optionData['nilai_numerik'] ?? null,
                                'urutan' => $optionData['urutan'] ?? $index,
                            ]);
                        }
                    } else {
                        // Create new option
                        SurveyQuestionOption::create([
                            'id_survey_question' => $surveyQuestion->id,
                            'opsi' => $optionData['opsi'],
                            'nilai_numerik' => $optionData['nilai_numerik'] ?? null,
                            'urutan' => $optionData['urutan'] ?? $index,
                        ]);
                    }
                }
            }

            $surveyQuestion->load('options');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pertanyaan survey berhasil diperbarui',
                'data' => $surveyQuestion,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'survey_question_option_unique')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Opsi dengan kombinasi opsi dan nilai numerik yang sama sudah ada untuk pertanyaan ini.',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pertanyaan survey: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pertanyaan survey: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(SurveyQuestion $surveyQuestion): JsonResponse
    {
        $surveyQuestion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pertanyaan survey dihapus',
        ]);
    }

    /**
     * Get pertanyaan survey berdasarkan ID survey (untuk mahasiswa)
     */
    public function getBySurvey(Request $request, int $id): JsonResponse
    {
        $questions = SurveyQuestion::with(['options'])
            ->where('id_survey', $id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }
}

