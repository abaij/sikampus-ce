<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perkuliahan', function (Blueprint $table): void {
            if (! Schema::hasColumn('perkuliahan', 'tanggal')) {
                $table->date('tanggal')->nullable()->after('id_jadwal');
            }
            if (! Schema::hasColumn('perkuliahan', 'pertemuan_ke')) {
                $table->unsignedSmallInteger('pertemuan_ke')->nullable()->after('tanggal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('perkuliahan', function (Blueprint $table): void {
            if (Schema::hasColumn('perkuliahan', 'pertemuan_ke')) {
                $table->dropColumn('pertemuan_ke');
            }
            if (Schema::hasColumn('perkuliahan', 'tanggal')) {
                $table->dropColumn('tanggal');
            }
        });
    }
};
