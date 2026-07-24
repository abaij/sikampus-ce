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
        Schema::create('pmb_prodi_pilih', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pendaftaran')->constrained('pmb_pendaftaran')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_prodi')->constrained('prodi')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_pendaftaran', 'id_prodi'], 'unique_prodi_pilih');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_prodi_pilih');
    }
};
