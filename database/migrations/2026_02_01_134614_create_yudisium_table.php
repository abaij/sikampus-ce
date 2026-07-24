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
        Schema::create('yudisium', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('id_jenis_keluar')->constrained('jenis_keluar')->restrictOnDelete();
            $table->string('tgl_keluar')->nullable();
            $table->string('no_ijazah')->nullable();
            $table->string('no_sk_yudisium')->nullable();
            $table->string('tanggal_sk_yudisium')->nullable();
            $table->decimal('ipk', 5, 2)->nullable();
            $table->string('judul_skripsi')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_mahasiswa', 'id_jenis_keluar'], 'yudisium_unique');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yudisium');
    }
};
