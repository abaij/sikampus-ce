<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\JenisKeringananBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;

class KeringananBiayaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_jenis_keringanan_biaya' => JenisKeringananBiaya::factory(),
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_semester' => Semester::factory(),
            'id_aturan_akses_keuangan' => null,
            'nominal' => 0,
            'status' => 'pending',
        ];
    }
}
