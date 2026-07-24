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
        Schema::create('wisuda_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('id_wisuda')->constrained('wisuda')->restrictOnDelete();
            $table->string('no_sk_wisuda')->nullable();
            $table->string('tanggal_sk_wisuda')->nullable();
            $table->string('file_sk_wisuda')->nullable();
            $table->string('foto')->nullable();
            $table->string('status')->default('pending'); // pending, acc, approved, rejected
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_mahasiswa', 'id_wisuda'], 'wisuda_mahasiswa_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wisuda_mahasiswa');
    }
};
