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
        Schema::table('prodi', function (Blueprint $table) {
            $table->foreignId('id_semester_aktif')->nullable()->constrained('semester')->restrictOnDelete()->cascadeOnUpdate()->after('id_jenjang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->dropForeign(['id_semester_aktif']);
            $table->dropColumn('id_semester_aktif');
        });
    }
};
