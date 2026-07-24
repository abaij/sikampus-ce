<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Dosen;
use App\Models\Mahasiswa;

class DosenWaliFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_dosen' => Dosen::factory(),
            'id_mahasiswa' => Mahasiswa::factory(),
            'status' => 'active',
        ];
    }
}
