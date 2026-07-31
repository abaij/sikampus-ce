<?php

namespace App\Support\Plugins;

/**
 * Registry sederhana untuk widget yang di-push plugin (lewat boot() provider-nya)
 * agar tampil di halaman dashboard superadmin. Satu instance singleton per boot
 * aplikasi — plugin cukup push() HTML/hasil render view, dashboard.blade.php
 * merendernya lewat View::composer('dashboard', ...) di AppServiceProvider.
 */
class DashboardWidgetRegistry
{
    /** @var list<string> */
    private array $widgets = [];

    public function push(string $html): void
    {
        $this->widgets[] = $html;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->widgets;
    }
}
