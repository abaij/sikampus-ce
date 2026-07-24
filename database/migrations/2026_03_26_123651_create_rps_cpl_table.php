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
        Schema::create('rps_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_rps')->constrained('rps')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('cpl')->nullable();
            $table->text('cpl_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rps_cpl');
    }
};
