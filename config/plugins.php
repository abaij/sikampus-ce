<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plugin System
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk sistem plugin yang diinstal lewat panel superadmin
    | (upload ZIP, extract ke install_path/<slug>, daftarkan service
    | provider-nya secara dinamis). Lihat app/Services/Plugins dan
    | app/Support/Plugins.
    |
    */

    'max_zip_size_kb' => (int) env('PLUGIN_MAX_ZIP_SIZE_KB', 20480),

    'max_extracted_size_kb' => (int) env('PLUGIN_MAX_EXTRACTED_SIZE_KB', 102400),

    // storage/app/plugins, bukan base_path('plugins') di root project — di
    // hampir semua deployment Laravel (Forge, script deploy standar) hanya
    // storage/ dan bootstrap/cache/ yang diberi permission writable untuk
    // user web server; root project biasanya read-only bagi PHP. Menaruh
    // instalasi plugin di sini menghindari error mkdir() Permission denied
    // tanpa perlu chmod manual di server.
    'install_path' => storage_path('app/plugins'),

    'upload_disk' => env('PLUGIN_UPLOAD_DISK', 'local'),

];
