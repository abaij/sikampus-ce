<?php

namespace Database\Factories;

use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_survey' => Survey::factory(),
            'pertanyaan' => fake()->sentence().'?',
            'tipe' => 'essay',
        ];
    }
}
