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
        Schema::create('nilai_komponen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_krs')
                  ->constrained('krs')->cascadeOnDelete();
            $table->foreignId('id_jenis_penilaian')
                  ->constrained('jenis_penilaian')->restrictOnDelete();
            $table->decimal('nilai', 5, 2)->default(0);
            $table->foreignId('id_dosen')
                  ->constrained('dosen')->restrictOnDelete()->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        
            $table->unique(['id_krs', 'id_jenis_penilaian']);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_komponen');
    }
};
