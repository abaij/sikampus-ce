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
        Schema::create('struktur_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kategori_biaya')
                ->nullable()
                ->constrained('kategori_biaya')->restrictOnDelete();
            $table->foreignId('id_prodi')->nullable()->constrained('prodi')->restrictOnDelete();
            $table->foreignId('id_angkatan')->constrained('semester')->restrictOnDelete();
            $table->foreignId('id_periode')->constrained('semester')->restrictOnDelete();
            $table->foreignId('id_komponen_biaya')->nullable()->constrained('komponen_biaya')->restrictOnDelete()->after('id_angkatan');
            $table->integer('tahap')->default(1);
            $table->decimal('nominal', 12, 2)->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_kategori_biaya', 'id_prodi', 'id_angkatan', 'id_periode', 'id_komponen_biaya', 'tahap'], 'struktur_biaya_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('struktur_biaya');
    }
};
