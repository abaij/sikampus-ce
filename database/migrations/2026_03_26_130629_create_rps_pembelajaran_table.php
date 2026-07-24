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
        Schema::create('rps_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_rps')->constrained('rps')->restrictOnDelete()->cascadeOnUpdate();
            $table->integer('urutan_pertemuan')->nullable();
            $table->text('sub_cpmk')->nullable();
            $table->text('indikator_penilaian')->nullable();
            $table->text('bentuk_kriteria_penilaian')->nullable();
            $table->text('pembelajaran_sinkron')->nullable();
            $table->text('pembelajaran_asinkron')->nullable();
            $table->text('materi')->nullable();
            $table->text('materi_en')->nullable();
            $table->decimal('bobot', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rps_pembelajaran');
    }
};
