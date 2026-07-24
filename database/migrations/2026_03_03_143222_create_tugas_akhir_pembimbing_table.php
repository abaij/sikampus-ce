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
        Schema::create('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tugas_akhir')->constrained('tugas_akhir')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_dosen')->constrained('dosen')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('peran')->default('pembimbing'); // pembimbing, penguji
            $table->date('tanggal_penugasan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_tugas_akhir', 'id_dosen', 'peran'], 'tugas_akhir_pembimbing_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir_pembimbing');
    }
};
