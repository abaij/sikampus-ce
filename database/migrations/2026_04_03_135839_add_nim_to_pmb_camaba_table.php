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
            $table->string('nim')->nullable()->unique()->after('email');
        });

        Schema::table('pmb_daftar_ulang', function (Blueprint $table) {
            $table->string('file_bukti_bayar')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmb_camaba', function (Blueprint $table) {
            $table->dropColumn('nim');
        });

        Schema::table('pmb_daftar_ulang', function (Blueprint $table) {
            $table->dropColumn('file_bukti_bayar');
        });
    }
};
