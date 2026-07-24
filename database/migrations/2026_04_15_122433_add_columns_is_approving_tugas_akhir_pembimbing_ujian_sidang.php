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
        Schema::table('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->boolean('is_approving')->default(false)->after('tanggal_penugasan');
        });

        Schema::table('ujian_sidang', function (Blueprint $table) {
            $table->string('file_proposal')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->dropColumn('is_approving');
        });

        Schema::table('ujian_sidang', function (Blueprint $table) {
            $table->dropColumn('file_proposal');
        });
    }
};
