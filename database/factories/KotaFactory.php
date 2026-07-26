<?php

namespace Database\Factories;

use App\Models\Provinsi;
use Illuminate\Database\Eloquent\Factories\Factory;

class KotaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->numerify('####'),
            'nama' => $this->faker->unique()->city(),
            'id_provinsi' => Provinsi::factory(),
        ];
    }
}
