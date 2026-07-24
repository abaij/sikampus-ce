<?php

namespace Database\Seeders;

use App\Models\Pekerjaan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PekerjaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pekerjaan::create([
            'nama' => 'Tidak bekerja',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Nelayan',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Petani',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Peternak',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'PNS/TNI/Polri',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Karyawan Swasta',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Pedagang Kecil',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Pedagang Besar',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Wiraswasta',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Wirausaha',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Buruh',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Pensiunan',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Sudah Meninggal',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Pekerjaan::create([
            'nama' => 'Lainnya',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
