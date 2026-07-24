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
        Schema::create('rps_subcpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cpmk')->constrained('rps_cpmk')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('subcpmk')->nullable();
            $table->text('subcpmk_en')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rps_subcpmk');
    }
};
