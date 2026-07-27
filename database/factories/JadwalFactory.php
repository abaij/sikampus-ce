<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_kelas' => Kelas::factory(),
            'hari' => fake()->randomElement(['senin', 'selasa', 'rabu', 'kamis', 'jumat']),
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'urutan_pertemuan' => fake()->unique()->numberBetween(1, 99),
            'is_active' => false,
        ];
    }
}
