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
        Schema::create('ujian_sidang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tugas_akhir')->constrained('tugas_akhir')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_semester')->constrained('semester')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('tanggal_daftar')->default(now());
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected
            $table->timestamp('tanggal_ujian_mulai')->nullable();
            $table->timestamp('tanggal_ujian_selesai')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_tugas_akhir', 'id_semester'], 'ujian_sidang_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ujian_sidang');
    }
};
