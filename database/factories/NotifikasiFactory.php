<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotifikasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_user' => User::factory(),
            'tipe' => 'krs_diajukan',
            'judul' => $this->faker->sentence(3),
            'pesan' => $this->faker->sentence(10),
            'url' => null,
            'dibaca_pada' => null,
        ];
    }
}
