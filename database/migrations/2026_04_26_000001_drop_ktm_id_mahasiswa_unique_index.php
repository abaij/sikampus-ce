<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus unique global id_mahasiswa agar setelah soft delete, mahasiswa boleh punya KTM baru.
     * Satu KTM aktif (deleted_at null) per mahasiswa dijaga lewat validasi aplikasi.
     */
    public function up(): void
    {
        Schema::table('ktm', function (Blueprint $table) {
            $table->dropForeign(['id_mahasiswa']);
        });
        Schema::table('ktm', function (Blueprint $table) {
            $table->dropUnique('ktm_unique');
        });
        Schema::table('ktm', function (Blueprint $table) {
            $table->index('id_mahasiswa', 'ktm_id_mahasiswa_index');
        });
        Schema::table('ktm', function (Blueprint $table) {
            $table->foreign('id_mahasiswa')
                ->references('id')
                ->on('mahasiswa')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ktm', function (Blueprint $table) {
            $table->dropForeign(['id_mahasiswa']);
        });
        Schema::table('ktm', function (Blueprint $table) {
            $table->dropIndex('ktm_id_mahasiswa_index');
        });
        Schema::table('ktm', function (Blueprint $table) {
            $table->unique(['id_mahasiswa'], 'ktm_unique');
        });
        Schema::table('ktm', function (Blueprint $table) {
            $table->foreign('id_mahasiswa')
                ->references('id')
                ->on('mahasiswa')
                ->restrictOnDelete();
        });
    }
};
