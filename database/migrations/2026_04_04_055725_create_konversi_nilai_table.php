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
        Schema::create('konversi_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('id_kurikulum')->constrained('kurikulum')->restrictOnDelete();
            $table->foreignId('id_jenis_konversi')->constrained('jenis_konversi_nilai')->restrictOnDelete();
            $table->foreignId('id_nilai')->nullable()->constrained('nilai')->restrictOnDelete();
            $table->string('kode_mk_lama')->nullable();
            $table->string('nama_mk_lama')->nullable();
            $table->integer('sks_lama')->default(1);
            $table->string('nilai_lama', 5);
            $table->string('kode_mk_baru')->nullable();
            $table->string('nama_mk_baru')->nullable();
            $table->integer('sks_baru')->default(1);
            $table->string('nilai_baru', 5);
            $table->string('keterangan')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_mahasiswa', 'kode_mk_lama', 'kode_mk_baru'], 'konversi_nilai_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('konversi_nilai');
    }
};
