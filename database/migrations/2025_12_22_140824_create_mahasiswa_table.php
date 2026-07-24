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
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->nullable()->unique()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('nama');
            $table->string('nim')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('no_wa')->nullable();
            $table->string('handphone')->nullable();
            $table->string('alamat')->nullable();
            $table->string('kode_pos')->nullable();
            $table->unsignedBigInteger('id_semester_masuk')->nullable();
            $table->foreignId('id_prodi')->nullable()->constrained('prodi')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_grup_mahasiswa')->nullable()->constrained('grup_mahasiswa')->nullOnDelete()->cascadeOnUpdate();
            $table->string('id_kecamatan')->nullable();
            $table->string('id_kota')->nullable();
            $table->string('id_provinsi')->nullable();
            $table->string('id_negara')->nullable();
            $table->string('foto')->nullable();
            $table->foreignId('id_status_akademik')->nullable()->constrained('status_akademik')->nullOnDelete()->cascadeOnUpdate();
            $table->string('jenis_kelamin')->nullable();
            $table->string('id_tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('no_ktp')->nullable();
            $table->string('sekolah_asal')->nullable();
            $table->string('nis')->nullable();
            $table->string('nisn')->nullable();
            $table->string('npwp')->nullable();
            $table->string('mulai_semester')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('dusun')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('penerima_kps')->nullable();
            $table->string('no_kps')->nullable();
            $table->string('ayah')->nullable();
            $table->string('nik_ayah')->nullable();
            $table->date('tgl_lahir_ayah')->nullable();
            $table->unsignedBigInteger('id_pddk_ayah')->nullable();
            $table->unsignedBigInteger('id_pekerjaan_ayah')->nullable();
            $table->unsignedBigInteger('id_penghasilan_ayah')->nullable();
            $table->string('ibu')->nullable();
            $table->string('nik_ibu')->nullable();
            $table->date('tgl_lahir_ibu')->nullable();
            $table->unsignedBigInteger('id_pddk_ibu')->nullable();
            $table->unsignedBigInteger('id_pekerjaan_ibu')->nullable();
            $table->unsignedBigInteger('id_penghasilan_ibu')->nullable();
            $table->string('wali')->nullable();
            $table->string('nik_wali')->nullable();
            $table->date('tgl_lahir_wali')->nullable();
            $table->unsignedBigInteger('id_pddk_wali')->nullable();
            $table->unsignedBigInteger('id_pekerjaan_wali')->nullable();
            $table->unsignedBigInteger('id_penghasilan_wali')->nullable();
            $table->decimal('jml_biaya_masuk', 12, 2)->nullable();
            $table->integer('sks_diakui')->nullable();
            $table->unsignedBigInteger('id_jalur_masuk')->nullable(); // SNMPTN, SBMPTN, Mandiri, Lainnya
            $table->unsignedBigInteger('id_jenis_daftar')->nullable(); // Pendaftaran Online, Pendaftaran Offline, Lainnya
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
        Schema::dropIfExists('mahasiswa');
    }
};
