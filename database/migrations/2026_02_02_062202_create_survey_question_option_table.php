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
        Schema::create('survey_question_option', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_survey_question')->constrained('survey_question')->cascadeOnDelete();
            $table->string('opsi');
            $table->integer('nilai_numerik')->nullable();
            $table->integer('urutan')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_survey_question', 'opsi','nilai_numerik'],'survey_question_option_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_question_option');
    }
};
