<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Tagihan;

class PembayaranFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_tagihan' => Tagihan::factory(),
            'no_pembayaran' => 'PAY-'.fake()->unique()->numerify('########'),
            'nominal' => 500000,
            'tanggal_pembayaran' => now(),
            'metode_pembayaran' => 'transfer',
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
