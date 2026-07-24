<?php

use App\Models\PmbDaftarUlang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status alur herregistrasi/daftar ulang disimpan di camaba (bukan di pmb_pendaftaran).
     * Nilai: pending | herregistrasi (menggantikan acc pada baris pmb_daftar_ulang untuk tampilan & aturan bisnis).
     */
    public function up(): void
    {
        Schema::table('pmb_camaba', function (Blueprint $table) {
            $table->string('status_herregistrasi', 50)->nullable()->after('nim');
        });

        PmbDaftarUlang::query()
            ->whereNull('deleted_at')
            ->whereIn('status', ['acc', 'pending'])
            ->with(['pendaftaran.camaba'])
            ->each(function (PmbDaftarUlang $du): void {
                $camaba = $du->pendaftaran?->camaba;
                if (! $camaba) {
                    return;
                }
                $raw = $du->getAttributes()['status'] ?? null;
                $camaba->update([
                    'status_herregistrasi' => $raw === 'acc' ? 'herregistrasi' : 'pending',
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmb_camaba', function (Blueprint $table) {
            $table->dropColumn('status_herregistrasi');
        });
    }
};
