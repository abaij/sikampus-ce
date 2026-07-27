<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKonversiNilaiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Jenis Konversi '.fake()->unique()->word(),
            'keterangan' => null,
            'is_aktif' => true,
        ];
    }
}
