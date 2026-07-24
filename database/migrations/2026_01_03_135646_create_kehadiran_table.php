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
        Schema::create('kehadiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_perkuliahan')->constrained('perkuliahan')->restrictOnDelete();
            $table->foreignId('id_mhs')->constrained('mahasiswa')->restrictOnDelete();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('hadir'); // hadir, izin, alfa, sakit, alpha
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_perkuliahan', 'id_mhs']);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kehadiran');
    }
};
