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
        Schema::create('pmb_camaba', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->nullable()->constrained('pmb_users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('id_kota_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable();
            $table->string('no_hp')->nullable();
            $table->string('no_wa')->nullable();
            $table->string('alamat')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('dusun')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('id_kota')->nullable();
            $table->string('id_kecamatan')->nullable();
            $table->string('id_provinsi')->nullable();
            $table->foreignId('id_negara')->nullable()->constrained('negara')->nullOnDelete()->cascadeOnUpdate();
            $table->string('foto')->nullable();
            $table->string('no_ktp')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('no_npwp')->nullable();
            $table->string('no_sim')->nullable();
            $table->string('no_kps')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('nama_wali')->nullable();
            $table->string('no_hp_ayah')->nullable();
            $table->string('no_hp_ibu')->nullable();
            $table->string('no_hp_wali')->nullable();
            $table->string('alamat_ayah')->nullable();
            $table->string('alamat_ibu')->nullable();
            $table->string('alamat_wali')->nullable();
            $table->foreignId('id_agama')->nullable()->constrained('agama')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('status_perkawinan', ['Tidak Kawin', 'Kawin'])->nullable();
            $table->enum('kewarganegaraan', ['WNI', 'WNA'])->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->string('status')->default('active');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_user']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_camaba');
    }
};
