<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Semester;
use App\Models\KomponenBiaya;

class StrukturBiayaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_kategori_biaya' => null,
            'id_prodi' => null,
            'id_angkatan' => Semester::factory(),
            'id_periode' => Semester::factory(),
            'id_komponen_biaya' => KomponenBiaya::factory(),
            'tahap' => 1,
            'nominal' => 1000000,
        ];
    }
}
