<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $negara = database_path('sql/ref_negara.sql');
        $negara_sql = File::get($negara);
        DB::unprepared($negara_sql);
        $this->command->info('Data SQL negara berhasil dieksekusi');

        $provinsi = database_path('sql/ref_provinsi.sql');
        $provinsi_sql = File::get($provinsi);
        DB::unprepared($provinsi_sql);
        $this->command->info('Data SQL provinsi berhasil dieksekusi');

        $kota = database_path('sql/ref_kota.sql');
        $kota_sql = File::get($kota);
        DB::unprepared($kota_sql);
        $this->command->info('Data SQL kota berhasil dieksekusi');
        
        $kecamatan = database_path('sql/ref_kecamatan.sql');
        $kecamatan_sql = File::get($kecamatan);
        DB::unprepared($kecamatan_sql);
        $this->command->info('Data SQL kecamatan berhasil dieksekusi');
    }
}
