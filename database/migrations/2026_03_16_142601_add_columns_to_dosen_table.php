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
        Schema::table('dosen', function (Blueprint $table) {
            $table->string('nidk')->nullable()->after('nidn');
            $table->string('nupn')->nullable()->after('nidk');
            $table->integer('kuota_bimbingan_akademik')->default(0)->after('foto');
            $table->integer('kuota_bimbingan_ta')->default(0)->after('kuota_bimbingan_akademik');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            $table->dropColumn('nidk');
            $table->dropColumn('nupn');
            $table->dropColumn('kuota_bimbingan_akademik');
            $table->dropColumn('kuota_bimbingan_ta');
        });
    }
};
