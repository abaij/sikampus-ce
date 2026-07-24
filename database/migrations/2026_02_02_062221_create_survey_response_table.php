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
        Schema::create('survey_response', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_survey')->constrained('survey')->cascadeOnDelete();
            $table->foreignId('id_mahasiswa')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignId('id_krs')->nullable()->constrained('krs')->nullOnDelete();
            $table->timestamp('tanggal_submit')->default(now());
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_survey', 'id_mahasiswa', 'id_krs'],'survey_response_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_response');
    }
};
