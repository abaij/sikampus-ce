<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Wisuda;
use App\Models\Mahasiswa;

class WisudaMahasiswaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_wisuda' => Wisuda::factory(),
            'id_mahasiswa' => Mahasiswa::factory(),
            'no_sk_wisuda' => 'SKW/'.fake()->unique()->numerify('###').'/2026',
            'status' => 'terdaftar',
        ];
    }
}
