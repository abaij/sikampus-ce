<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\KategoriBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;

class KategoriBiayaMahasiswaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_kategori_biaya' => KategoriBiaya::factory(),
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_semester' => Semester::factory(),
            'status' => 'active',
        ];
    }
}
