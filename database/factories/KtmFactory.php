<?php

namespace Database\Factories;

use App\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class KtmFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_mahasiswa' => Mahasiswa::factory(),
            'nomor_ktm' => fake()->unique()->numerify('KTM-########'),
            'file' => null,
            'status' => 'active',
        ];
    }
}
