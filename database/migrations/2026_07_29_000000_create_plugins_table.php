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
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('version');
            $table->text('description')->nullable();
            $table->string('provider_class');
            $table->string('source_path');
            $table->boolean('has_web_routes')->default(false);
            $table->boolean('has_api_routes')->default(false);
            $table->string('migrations_relative_path')->nullable();
            $table->string('checksum')->nullable();
            $table->boolean('enabled')->default(false);
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_migrated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
