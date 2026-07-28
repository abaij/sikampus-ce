<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Ujian;
use Illuminate\Database\Eloquent\Factories\Factory;

class UjianFactory extends Factory
{
    public function definition(): array
    {
        $kelas = Kelas::factory();

        return [
            'id_kelas' => $kelas,
            'jenis_ujian' => fake()->randomElement(Ujian::JENIS),
            'id_semester' => fn (array $attrs) => Kelas::find($attrs['id_kelas'])?->id_semester,
        ];
    }
}
