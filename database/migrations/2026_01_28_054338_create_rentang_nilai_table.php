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
        Schema::create('rentang_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenjang')
                  ->constrained('jenjang')->cascadeOnDelete();
            $table->string('nilai_huruf');
            $table->decimal('nilai_angka', 5, 2);
            $table->decimal('nilai_rendah', 5, 2);
            $table->decimal('nilai_tinggi', 5, 2);
            $table->boolean('is_lulus')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_jenjang', 'nilai_huruf']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentang_nilai');
    }
};
