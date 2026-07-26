<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisDaftarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->words(2, true),
        ];
    }
}
