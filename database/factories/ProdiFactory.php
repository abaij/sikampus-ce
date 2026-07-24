<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Fakultas;
use App\Models\Jenjang;

class ProdiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Prodi '.fake()->unique()->word(),
            'kode' => fake()->unique()->lexify('PR-????'),
            'id_fakultas' => Fakultas::factory(),
            'id_jenjang' => Jenjang::factory(),
        ];
    }
}
