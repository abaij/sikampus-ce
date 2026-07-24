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
        Schema::create('pmb_pengaturan_tes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_periode')->constrained('pmb_periode')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_jenis_tes')->constrained('pmb_jenis_tes')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_jalur_masuk')->constrained('jalur_masuk')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('bobot', 5, 2)->nullable();
            $table->integer('urutan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_periode', 'id_jenis_tes', 'id_jalur_masuk'], 'unique_pengaturan_tes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_pengaturan_tes');
    }
};
