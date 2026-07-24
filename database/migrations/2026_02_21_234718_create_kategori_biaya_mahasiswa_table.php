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
        Schema::create('kategori_biaya_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kategori_biaya')->constrained('kategori_biaya')->restrictOnDelete();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('id_semester')->constrained('semester')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_kategori_biaya', 'id_mahasiswa', 'id_semester'], 'kategori_biaya_mahasiswa_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_biaya_mahasiswa');
    }
};
