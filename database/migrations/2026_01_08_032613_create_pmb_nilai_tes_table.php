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
        Schema::create('pmb_nilai_tes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pendaftaran')->constrained('pmb_pendaftaran')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_jenis_tes')->constrained('pmb_jenis_tes')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->text('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_pendaftaran', 'id_jenis_tes'], 'unique_nilai_tes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_nilai_tes');
    }
};
