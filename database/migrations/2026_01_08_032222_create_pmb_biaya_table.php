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
        Schema::create('pmb_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_periode')->constrained('pmb_periode')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->decimal('jumlah', 12, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();    

            $table->unique(['nama'], 'pmb_biaya_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_biaya');
    }
};
