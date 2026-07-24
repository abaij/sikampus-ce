<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KomponenBiayaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('KOMP-????'),
            'nama' => 'Komponen '.fake()->unique()->word(),
            'is_per_semester' => true,
            'is_akademik' => false,
        ];
    }
}
