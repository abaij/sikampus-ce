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
        Schema::table('nilai', function (Blueprint $table) {
            $table->foreignId('id_konversi_nilai')->nullable()->after('id_krs')->constrained('konversi_nilai')->restrictOnDelete();

            $table->unique(['id_konversi_nilai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai', function (Blueprint $table) {
            $table->dropForeign(['id_konversi_nilai']);
            $table->dropUnique(['id_konversi_nilai']);
            $table->dropColumn('id_konversi_nilai');
        });
    }
};
