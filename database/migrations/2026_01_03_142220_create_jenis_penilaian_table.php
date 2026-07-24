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
        Schema::create('jenis_penilaian', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // UTS, UAS, TUGAS, PRAKTIKUM
            $table->unsignedTinyInteger('bobot')->default(0);
            $table->string('nama');
            $table->string('status')->default('manual'); // active = diisi dosen, inactive = otomatis oleh sistem
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
