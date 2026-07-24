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
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mahasiswa')
                  ->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('id_semester')
                  ->constrained('semester')->restrictOnDelete();
            $table->string('no_tagihan')->unique();
            $table->decimal('total', 12, 2);
            $table->string('status')->default('unpaid'); // unpaid, paid, expired
            $table->timestamp('tanggal_tagihan')->default(now());
            $table->timestamp('tanggal_jatuh_tempo')->nullable();
            $table->timestamp('tanggal_pembayaran')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        
            $table->unique(['id_mahasiswa', 'id_semester', 'no_tagihan'],'tagihan_unique');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
