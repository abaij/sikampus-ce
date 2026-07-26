<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProvinsiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->numerify('##'),
            'nama' => $this->faker->unique()->state(),
        ];
    }
}
