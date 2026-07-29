<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap tagihan sebelumnya hanya disimpan sebagai penanda teks "[TAHAP:n]" di kolom
     * keterangan oleh TagihanController::generateFromStrukturBiaya. Akibatnya aturan "satu
     * tagihan per (mahasiswa, semester)" di store()/update() bertabrakan dengan generator yang
     * memang membuat satu tagihan per tahap — tagihan multi-tahap jadi tidak bisa diedit sama
     * sekali. Menjadikan tahap kolom sungguhan membuat aturan uniknya bisa memasukkan tahap,
     * sekaligus menghapus ketergantungan deteksi duplikat pada isi keterangan yang bebas diedit.
     */
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->unsignedInteger('tahap')->nullable()->after('total');
            $table->index(['id_mahasiswa', 'id_semester', 'tahap'], 'tagihan_mhs_semester_tahap_index');
        });

        // Backfill dari penanda lama. Sengaja memakai SUBSTRING_INDEX, bukan REGEXP_SUBSTR:
        // hasilnya sama untuk pola "[TAHAP:n]" tapi tidak bergantung pada MySQL 8 dan tidak
        // punya jebakan escaping regex di dalam string SQL.
        DB::statement("
            UPDATE tagihan
            SET tahap = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, '[TAHAP:', -1), ']', 1) AS UNSIGNED)
            WHERE keterangan LIKE '%[TAHAP:%'
        ");
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropIndex('tagihan_mhs_semester_tahap_index');
            $table->dropColumn('tahap');
        });
    }
};
