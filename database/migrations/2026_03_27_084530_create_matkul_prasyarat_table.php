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
        Schema::create('matkul_prasyarat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_matkul')->constrained('matkul')->cascadeOnDelete();
            $table->foreignId('id_matkul_prasyarat')->constrained('matkul')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_matkul', 'id_matkul_prasyarat'], 'matkul_prasyarat_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matkul_prasyarat');
    }
};
