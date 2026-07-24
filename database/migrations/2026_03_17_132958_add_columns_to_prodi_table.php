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
            $table->string('nama_en')->nullable()->after('nama');
            $table->integer('sks_minimal')->default(0)->after('kode');
            $table->decimal('ipk_lulus_minimal', 5, 2)->default(0)->after('sks_minimal');
            $table->string('gelar')->nullable()->after('ipk_lulus_minimal');
            $table->string('gelar_singkat')->nullable()->after('gelar');
            $table->tinyInteger('maks_dosen_pembimbing')->default(1)->after('gelar_singkat');
            $table->tinyInteger('maks_dosen_penguji')->default(1)->after('maks_dosen_pembimbing');
            $table->boolean('is_pmb_open')->default(true)->after('maks_dosen_penguji');
            $table->string('status')->default('active')->after('is_pmb_open');
            $table->foreignId('id_sekprodi')->nullable()->constrained('dosen')->nullOnDelete()->cascadeOnUpdate()->after('id_kaprodi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->dropForeign(['id_sekprodi']);
            $table->dropColumn('nama_en');
            $table->dropColumn('sks_minimal');
            $table->dropColumn('ipk_lulus_minimal');
            $table->dropColumn('gelar');
            $table->dropColumn('gelar_singkat');
            $table->dropColumn('maks_dosen_pembimbing');
            $table->dropColumn('maks_dosen_penguji');
            $table->dropColumn('is_pmb_open');
            $table->dropColumn('status');
            $table->dropColumn('id_sekprodi');
        });
    }
};
