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
        Schema::create('keringanan_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_keringanan_biaya')->constrained('jenis_keringanan_biaya')->restrictOnDelete();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('id_semester')->constrained('semester')->restrictOnDelete();
            $table->foreignId('id_aturan_akses_keuangan')->nullable()->constrained('aturan_akses_keuangan')->restrictOnDelete();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->string('file_lampiran')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamp('tanggal_pengajuan')->nullable();
            $table->timestamp('tanggal_approved')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_jenis_keringanan_biaya', 'id_mahasiswa', 'id_semester'], 'keringanan_biaya_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keringanan_biaya');
    }
};
