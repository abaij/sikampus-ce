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
        Schema::create('bobot_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kurikulum_matkul')
                  ->constrained('kurikulum_matkul')->cascadeOnDelete();
            $table->foreignId('id_jenis_penilaian')
                  ->constrained('jenis_penilaian')->restrictOnDelete();
            $table->decimal('bobot', 5, 2); // persen (misal 30.00)
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique([
                'id_kurikulum_matkul',
                'id_jenis_penilaian'
            ]);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bobot_penilaian');
    }
};
