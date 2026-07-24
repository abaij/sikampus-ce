<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Jenjang;

class RentangNilaiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_jenjang' => Jenjang::factory(),
            'nilai_huruf' => 'A',
            'nilai_angka' => 4,
            'nilai_rendah' => 85,
            'nilai_tinggi' => 100,
            'is_lulus' => true,
        ];
    }
}
