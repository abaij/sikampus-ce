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
        Schema::create('survey_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_survey')->constrained('survey')->cascadeOnDelete();
            $table->text('pertanyaan');
            $table->string('tipe')->nullable(); // likert, essay, multiple choice, single choice
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
        Schema::dropIfExists('survey_question');
    }
};
