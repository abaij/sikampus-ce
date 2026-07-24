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
        Schema::create('pmb_daftar_ulang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pendaftaran')->constrained('pmb_pendaftaran')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_prodi')->constrained('prodi')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('tanggal_daftar_ulang')->nullable();
            $table->string('status')->nullable(); // pending, pushed, gagal
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_pendaftaran']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_daftar_ulang');
    }
};
