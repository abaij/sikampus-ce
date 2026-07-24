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
        Schema::create('kurikulum_matkul', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kurikulum')->constrained('kurikulum')->restrictOnDelete();
            $table->foreignId('id_matkul')->constrained('matkul')->restrictOnDelete();
            $table->string('kode_matkul')->nullable();
            $table->string('nama_matkul')->nullable();
            $table->string('nama_matkul_en')->nullable();
            $table->integer('sks')->nullable();
            $table->unsignedTinyInteger('semester_rekomendasi')->nullable();
            $table->boolean('is_wajib')->default(true)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_kurikulum', 'id_matkul']);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kurikulum_matkul');
    }
};
