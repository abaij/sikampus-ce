<?php

namespace Database\Seeders;

use App\Models\Agama;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AgamaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Agama::create([
            'nama' => 'Islam',
            'status' => 'active',
        ]);
        Agama::create([
            'nama' => 'Kristen',
            'status' => 'active',
        ]);
        Agama::create([
            'nama' => 'Katolik',
            'status' => 'active',
        ]);
        Agama::create([
            'nama' => 'Hindu',
            'status' => 'active',
        ]);
        Agama::create([
            'nama' => 'Budha',
            'status' => 'active',
        ]);
        Agama::create([
            'nama' => 'Konghucu',
            'status' => 'active',
        ]);
    }
}
