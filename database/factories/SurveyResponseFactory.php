<?php

namespace Database\Factories;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_survey' => Survey::factory(),
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_krs' => Krs::factory(),
            'tanggal_submit' => now(),
        ];
    }
}
