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
        Schema::create('ujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kelas')->constrained('kelas')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('jenis_ujian', ['UTS', 'UAS', 'PRAKTIKUM']);
            $table->foreignId('id_ruangan')->nullable()->constrained('ruangan')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_semester')->constrained('semester')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('tanggal_mulai')->nullable();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_kelas', 'id_semester', 'jenis_ujian'], 'ujian_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ujian');
    }
};
