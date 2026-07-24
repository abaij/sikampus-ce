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
        Schema::create('nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_krs')->nullable()->constrained('krs')->restrictOnDelete();
            $table->unsignedTinyInteger('sks')->nullable(); // SKS Mata Kuliah
            $table->decimal('angka_mutu', 5, 2)->nullable(); // Nilai angka * SKS
            $table->string('huruf_mutu')->nullable();
            $table->boolean('is_final')->default(false)->nullable();
            $table->tinyInteger('revisi')->default(0)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('id_krs');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};
