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
        Schema::create('matkul', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->string('nama');
            $table->string('nama_en')->nullable();
            $table->string('deskripsi')->nullable();
            $table->unsignedTinyInteger('sks')->default(2)->nullable();
            $table->unsignedTinyInteger('semester')->default(1)->nullable();
            $table->foreignId('id_prodi')->nullable()->constrained('prodi')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_jenis_matkul')->nullable()->constrained('jenis_matkul')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('status')->default('active'); // active, inactive
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kode', 'id_prodi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matkul');
    }
};
