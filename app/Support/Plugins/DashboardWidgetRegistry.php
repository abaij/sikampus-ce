<?php

namespace App\Support\Plugins;

use Closure;
use Throwable;

/**
 * Registry sederhana untuk widget yang di-push plugin (lewat boot() provider-nya)
 * agar tampil di halaman dashboard superadmin. Satu instance singleton per boot
 * aplikasi — plugin push() sebuah closure (bukan HTML yang sudah dirender),
 * dashboard.blade.php merendernya lewat View::composer('dashboard', ...) di
 * AppServiceProvider.
 *
 * PENTING kenapa closure, bukan string: push() dipanggil dari dalam boot()
 * provider plugin, di titik yang bisa lebih awal daripada saat Laravel selesai
 * membangun ulang lookup nama route (lihat PluginBootManager) — kalau widget
 * langsung dirender saat itu juga dan Blade-nya memanggil route() ke rute
 * milik plugin itu sendiri, hasilnya RouteNotFoundException meski rute itu
 * SUDAH terdaftar. Dengan menunda render lewat closure sampai all() benar-benar
 * dipanggil (saat dashboard.blade.php dirender, jauh setelah seluruh siklus
 * boot aplikasi selesai), route() & fasad lain yang butuh state request/boot
 * penuh sudah pasti aman dipakai di dalam view widget.
 */
class DashboardWidgetRegistry
{
    /** @var list<Closure(): string> */
    private array $widgets = [];

    public function push(Closure $renderer): void
    {
        $this->widgets[] = $renderer;
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_values(array_filter(array_map(function (Closure $renderer): ?string {
            try {
                return $renderer();
            } catch (Throwable $e) {
                // Satu widget plugin yang gagal dirender tidak boleh
                // mematikan seluruh halaman dashboard untuk semua user.
                report($e);

                return null;
            }
        }, $this->widgets)));
    }
}
