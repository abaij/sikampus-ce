<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perkuliahan')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE perkuliahan MODIFY realisasi_materi TEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE perkuliahan ALTER COLUMN realisasi_materi TYPE TEXT USING (realisasi_materi::text)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('perkuliahan')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE perkuliahan MODIFY realisasi_materi VARCHAR(255) NULL');
        }
    }
};
