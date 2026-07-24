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
        Schema::create('user_role_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user_role')->constrained('user_roles')->cascadeOnDelete();
            $table->unsignedBigInteger('id_scope'); // id_fakultas, id_prodi
            $table->string('scope_type'); // fakultas, prodi, dosen, mahasiswa
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
        //
    }
};
