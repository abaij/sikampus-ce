<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\KurikulumMatkul;
use App\Models\JenisPenilaian;

class BobotPenilaianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_kurikulum_matkul' => KurikulumMatkul::factory(),
            'id_jenis_penilaian' => JenisPenilaian::factory(),
            'bobot' => 50,
        ];
    }
}
