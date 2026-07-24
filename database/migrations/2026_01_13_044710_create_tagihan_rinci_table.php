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
        Schema::create('tagihan_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tagihan')
                  ->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('id_komponen_biaya')
                  ->constrained('komponen_biaya')->restrictOnDelete();
            $table->decimal('nominal', 12, 2);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        
            $table->unique([
                'id_tagihan',
                'id_komponen_biaya'
            ],'tagihan_rinci_unique');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan_rinci');
    }
};
