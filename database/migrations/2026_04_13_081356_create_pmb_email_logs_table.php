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
        Schema::create('pmb_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_camaba')->constrained('pmb_camaba')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('email');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmb_email_logs');
    }
};
