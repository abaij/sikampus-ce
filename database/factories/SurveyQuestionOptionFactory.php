<?php

namespace Database\Factories;

use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyQuestionOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_survey_question' => SurveyQuestion::factory(),
            'opsi' => fake()->words(2, true),
            'nilai_numerik' => fake()->numberBetween(1, 5),
            'urutan' => 0,
        ];
    }
}
