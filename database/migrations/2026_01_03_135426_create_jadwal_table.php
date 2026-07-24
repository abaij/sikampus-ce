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
        Schema::create('jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kelas')
                  ->constrained('kelas')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('id_jenis_kuliah')->nullable()->constrained('jenis_kuliah')->nullOnDelete()->cascadeOnUpdate();
            $table->date('tanggal')->nullable();
            $table->string('hari')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedBigInteger('id_ruangan')->nullable();
            $table->unsignedTinyInteger('urutan_pertemuan')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        
            $table->unique([
                'id_kelas',
                'id_ruangan',
                'urutan_pertemuan',
            ], 'jadwal_unique_slot');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};
