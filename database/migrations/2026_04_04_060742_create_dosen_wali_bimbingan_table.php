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
        Schema::create('dosen_wali_bimbingan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_dosen_wali')->constrained('dosen_wali')->restrictOnDelete();
            $table->foreignId('id_semester')->constrained('semester')->restrictOnDelete();
            $table->text('catatan_dosen')->nullable();
            $table->text('catatan_mhs')->nullable();
            $table->string('file')->nullable();
            $table->date('tanggal_bimbingan')->nullable();
            $table->timestamp('waktu_validasi_dosen')->nullable();
            $table->timestamp('waktu_validasi_mhs')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_wali_bimbingan');
    }
};
