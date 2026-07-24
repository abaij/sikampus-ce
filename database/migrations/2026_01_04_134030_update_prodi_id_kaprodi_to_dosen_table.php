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
        Schema::table('prodi', function (Blueprint $table) {
            // Drop existing foreign key constraint
            $table->dropForeign(['id_kaprodi']);
            
            // Add new foreign key constraint to dosen table
            $table->foreign('id_kaprodi')
                  ->references('id')
                  ->on('dosen')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            // Drop foreign key to dosen
            $table->dropForeign(['id_kaprodi']);
            
            // Restore foreign key to users
            $table->foreign('id_kaprodi')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });
    }
};
