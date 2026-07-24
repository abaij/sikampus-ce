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
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->foreignId('id_kurikulum_matkul')->constrained('kurikulum_matkul')->restrictOnDelete();
            $table->foreignId('id_prodi')->constrained('prodi')->restrictOnDelete();
            $table->foreignId('id_kelompok_kelas')->nullable()->constrained('kelompok_kelas')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_angkatan')->constrained('semester')->restrictOnDelete();
            $table->foreignId('id_semester')->constrained('semester')->restrictOnDelete();
            $table->foreignId('id_dosen_pic')->nullable()->constrained('dosen')->nullOnDelete()->cascadeOnUpdate();
            $table->integer('jml_pertemuan')->default(16);
            $table->boolean('is_mingguan')->default(true);
            $table->smallInteger('kuota')->default(0);
            $table->boolean('is_active')->default(false);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_kelompok_kelas', 'id_kurikulum_matkul', 'id_semester', 'id_angkatan'], 'kelas_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
