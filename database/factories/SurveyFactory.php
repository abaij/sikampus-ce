<?php

namespace Database\Factories;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Survey '.fake()->words(3, true),
            'kode' => fake()->unique()->regexify('SRV-[A-Z0-9]{6}'),
            'id_semester' => Semester::factory(),
            'is_active' => false,
        ];
    }
}
