<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Krs;

class NilaiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_krs' => Krs::factory(),
            'sks' => 3,
            'angka_mutu' => 4,
            'huruf_mutu' => 'A',
            'is_final' => null,
        ];
    }
}
