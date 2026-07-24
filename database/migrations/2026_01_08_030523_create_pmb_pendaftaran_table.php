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
        Schema::create('pmb_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_camaba')->constrained('pmb_camaba')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_periode')->constrained('pmb_periode')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('tanggal_pendaftaran')->nullable();
            $table->string('no_pendaftaran')->nullable();
            $table->string('status')->default('pending'); // pending, submitted, acc, lulus, gagal, batal, herregistrasi
            $table->text('keterangan')->nullable();
            $table->foreignId('id_jalur_masuk')->nullable()->constrained('jalur_masuk')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_jenis_daftar')->nullable()->constrained('jenis_daftar')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['no_pendaftaran', 'id_periode', 'id_camaba']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_pendaftaran');
    }
};
