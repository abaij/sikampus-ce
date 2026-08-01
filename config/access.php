<?php

/*
|--------------------------------------------------------------------------
| Granularitas hak akses panel admin
|--------------------------------------------------------------------------
|
| false (default/"basic"): satu permission "manage X" menjaga seluruh aksi (lihat, tambah,
| ubah, hapus) pada satu modul — skema yang sudah berjalan sejak awal, tidak berubah sama
| sekali kalau flag ini dibiarkan false.
|
| true ("full"): permission dipecah per aksi (view/create/update/delete X), dicek lewat
| App\Support\PanelAccess::can() dan entri `route_permissions_granular` di
| config/panel_access.php. Baru diterapkan untuk modul Keuangan > Tagihan sebagai pilot —
| modul lain tetap ikut skema dasar walau flag ini true, sampai dipetakan juga di
| route_permissions_granular.
|
*/

return [
    'granular_permissions' => env('GRANULAR_PERMISSIONS', false),
];
