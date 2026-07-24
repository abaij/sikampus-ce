<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\KurikulumMatkul;
use App\Models\Prodi;
use App\Models\Semester;

class KelasFactory extends Factory
{
    public function definition(): array
    {
        // Catatan: id_angkatan dan id_semester sengaja dibuat independen (dua baris semester
        // berbeda). Kalau test butuh keduanya sama, override eksplisit lewat state()/create([...]).
        return [
            'kode' => fake()->unique()->lexify('KLS-????'),
            'id_kurikulum_matkul' => KurikulumMatkul::factory(),
            'id_prodi' => Prodi::factory(),
            'id_angkatan' => Semester::factory(),
            'id_semester' => Semester::factory(),
            'is_active' => true,
        ];
    }
}
