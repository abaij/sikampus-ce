<?php

namespace Database\Factories;

use App\Models\KomponenBiaya;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagihanRinciFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_tagihan' => Tagihan::factory(),
            'id_komponen_biaya' => KomponenBiaya::factory(),
            'nominal' => 1000000,
        ];
    }
}
