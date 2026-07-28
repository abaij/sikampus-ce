<?php

namespace Database\Factories;

use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyResponseDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_survey_response' => SurveyResponse::factory(),
            'id_survey_question' => SurveyQuestion::factory(),
            'nilai_numerik' => fake()->numberBetween(1, 5),
        ];
    }
}
