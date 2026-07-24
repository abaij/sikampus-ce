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
        Schema::create('survey_response_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_survey_response')->constrained('survey_response')->cascadeOnDelete();
            $table->foreignId('id_survey_question')->constrained('survey_question')->cascadeOnDelete();
            $table->integer('nilai_numerik')->default(0);
            $table->text('nilai_text')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_response_detail');
    }
};
