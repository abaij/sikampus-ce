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
        Schema::create('dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('nama');
            $table->string('email')->unique()->nullable();
            $table->string('kode_dosen')->unique()->nullable();
            $table->string('nip')->unique()->nullable();
            $table->string('nidn')->unique()->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->unsignedBigInteger('agama')->nullable();
            $table->string('status_perkawinan')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('alamat')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('id_kota')->nullable();
            $table->string('id_provinsi')->nullable();
            $table->string('id_negara')->nullable();
            $table->string('foto')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen');
    }
};
