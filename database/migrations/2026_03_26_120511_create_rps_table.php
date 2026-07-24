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
        Schema::create('rps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kelas')->constrained('kelas')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('deskripsi_matkul')->nullable();
            $table->text('deskripsi_matkul_en')->nullable();
            $table->text('materi_kuliah')->nullable();
            $table->text('model_pembelajaran')->nullable();
            $table->text('pustaka_utama')->nullable();
            $table->text('pustaka_pendukung')->nullable();
            $table->text('media_perangkat_lunak')->nullable();
            $table->text('media_perangkat_keras')->nullable();
            $table->dateTime('tanggal_penyusunan')->nullable();
            $table->string('file_rps')->nullable();
            $table->string('created_by')->nullable();
            $table->string('verified_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_kelas'], 'rps_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rps');
    }
};
