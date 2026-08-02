<?php

namespace App\Models\Concerns;

use App\Services\PelakuAksi;
use Illuminate\Database\Eloquent\Model;

/**
 * Mengisi kolom jejak audit `created_by` / `updated_by` / `deleted_by` secara otomatis.
 *
 * Sebelumnya pengisiannya diserahkan ke tiap pemanggil, dan hasilnya di basis data ini:
 * `updated_by` dan `deleted_by` TIDAK PERNAH terisi sama sekali (nol baris, di semua tabel
 * keuangan) meski kolomnya ada dan sudah masuk $fillable, sementara `created_by` kosong pada
 * seluruh 833 pembayaran karena jalur import tidak mengisinya. Menempelkannya ke event model
 * membuat tidak ada jalur yang bisa lupa — termasuk jalur yang ditambahkan nanti.
 *
 * `created_by` memakai ??= supaya nilai yang sengaja diisi pemanggil tidak ditimpa.
 */
trait MencatatPelaku
{
    public static function bootMencatatPelaku(): void
    {
        static::creating(function (Model $model): void {
            $model->created_by ??= PelakuAksi::sekarang();
        });

        static::updating(function (Model $model): void {
            $model->updated_by = PelakuAksi::sekarang();
        });

        static::deleting(function (Model $model): void {
            // forceDelete menghapus barisnya sekaligus, jadi tidak ada gunanya dicatat.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            // Soft delete di Laravel menulis query UPDATE-nya sendiri yang hanya memuat
            // deleted_at, jadi atribut yang di-set di sini tidak akan ikut tersimpan —
            // harus disimpan lebih dulu. saveQuietly() supaya tidak memicu event updating.
            $model->deleted_by = PelakuAksi::sekarang();
            $model->saveQuietly();
        });
    }
}
