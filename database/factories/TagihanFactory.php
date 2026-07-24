<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Mahasiswa;
use App\Models\Semester;

class TagihanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_semester' => Semester::factory(),
            'no_tagihan' => 'TGH-'.fake()->unique()->numerify('########'),
            'total' => 1000000,
            'status' => 'unpaid',
        ];
    }
}
