<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kelompok_kelas')->nullable()->after('id_prodi');
            $table->foreign('id_kelompok_kelas')->references('id')->on('kelompok_kelas')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['id_kelompok_kelas']);
            $table->dropColumn('id_kelompok_kelas');
        });
    }
};
