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
        Schema::create('tugas_akhir_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tugas_akhir')->constrained('tugas_akhir')->onDelete('cascade');
            $table->string('status');
            $table->text('keterangan')->nullable();
            $table->foreignId('id_user')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir_status_logs');
    }
};
