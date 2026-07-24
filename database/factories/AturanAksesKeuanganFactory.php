<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AturanAksesKeuanganFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode_akses' => fake()->unique()->lexify('akses_????'),
            'nama' => 'Aturan '.fake()->unique()->word(),
            'persentase_minimum' => 75,
            'status' => 'active',
        ];
    }
}
