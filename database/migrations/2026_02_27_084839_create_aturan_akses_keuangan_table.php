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
        Schema::create('aturan_akses_keuangan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_akses');
            $table->string('nama')->nullable();
            $table->decimal('persentase_minimum', 5, 2)->nullable();
            $table->string('status')->default('active');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kode_akses'], 'aturan_akses_keuangan_kode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aturan_akses_keuangan');
    }
};
