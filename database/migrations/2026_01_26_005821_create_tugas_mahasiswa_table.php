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
        Schema::create('tugas_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tugas')->constrained('tugas')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('file')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('tanggal_submit')->nullable();
            $table->string('status')->default('submitted'); // submitted, accepted, rejected
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_tugas', 'id_mahasiswa']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tugas_mahasiswa');
    }
};
