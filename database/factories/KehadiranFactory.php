<?php

namespace Database\Factories;

use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use Illuminate\Database\Eloquent\Factories\Factory;

class KehadiranFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_perkuliahan' => Perkuliahan::factory(),
            'id_mhs' => Mahasiswa::factory(),
            'status' => 'hadir',
        ];
    }
}
