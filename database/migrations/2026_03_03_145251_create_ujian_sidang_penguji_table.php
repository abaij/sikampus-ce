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
        Schema::create('ujian_sidang_penguji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_ujian_sidang')->constrained('ujian_sidang')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_dosen')->constrained('dosen')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('is_ketua')->default(false);
            $table->text('catatan')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_ujian_sidang', 'id_dosen'], 'ujian_sidang_penguji_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ujian_sidang_penguji');
    }
};
