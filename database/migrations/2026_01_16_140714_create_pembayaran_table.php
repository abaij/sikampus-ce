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
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tagihan')
                  ->constrained('tagihan')->restrictOnDelete();
            $table->string('no_pembayaran')->unique();
            $table->decimal('nominal', 12, 2);
            $table->timestamp('tanggal_pembayaran')->default(now());
            $table->string('metode_pembayaran')->nullable(); // tunai, transfer, kartu, dll
            $table->string('bukti_bayar')->nullable(); // path to file
            $table->text('keterangan')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
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
        Schema::dropIfExists('pembayaran');
    }
};
