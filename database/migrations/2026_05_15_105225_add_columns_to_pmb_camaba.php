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
        Schema::table('pmb_camaba', function (Blueprint $table) {
            $table->string('nisn')->nullable()->after('asal_sekolah');
            $table->string('npsn')->nullable()->after('nisn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmb_camaba', function (Blueprint $table) {
            $table->dropColumn('nisn');
            $table->dropColumn('npsn');
        });
    }
};
