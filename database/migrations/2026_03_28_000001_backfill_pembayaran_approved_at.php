<?php

use App\Models\Pembayaran;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Pembayaran lama dianggap sudah disetujui agar perhitungan sisa tagihan (hanya approved) tetap benar.
     */
    public function up(): void
    {
        Pembayaran::query()
            ->whereNull('approved_at')
            ->whereNull('deleted_at')
            ->chunkById(200, function ($pembayarans) {
                foreach ($pembayarans as $p) {
                    $p->approved_at = $p->tanggal_pembayaran ?? $p->created_at;
                    if ($p->approved_by === null) {
                        $p->approved_by = 'sistem';
                    }
                    $p->save();
                }
            });
    }

    public function down(): void
    {
        // Tidak memutarbalikkan pengisian approved_at pada data produksi.
    }
};
