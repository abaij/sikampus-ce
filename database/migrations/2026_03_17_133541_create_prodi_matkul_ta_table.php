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
        Schema::create('prodi_matkul_ta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_prodi')->constrained('prodi')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_matkul')->constrained('matkul')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('has_ujian')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_prodi', 'id_matkul'], 'prodi_matkul_ta_unique');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi_matkul_ta');
    }
};
