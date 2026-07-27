<?php

namespace Database\Factories;

use App\Models\Jadwal;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerkuliahanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_jadwal' => Jadwal::factory(),
            'waktu_mulai' => fake()->dateTimeBetween('-2 months', 'now'),
            'materi' => fake()->sentence(),
        ];
    }
}
