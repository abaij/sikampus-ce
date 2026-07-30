<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plugin System
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk sistem plugin yang diinstal lewat panel superadmin
    | (upload ZIP, extract ke plugins/<slug>, daftarkan service provider-nya
    | secara dinamis). Lihat app/Services/Plugins dan app/Support/Plugins.
    |
    */

    'max_zip_size_kb' => (int) env('PLUGIN_MAX_ZIP_SIZE_KB', 20480),

    'max_extracted_size_kb' => (int) env('PLUGIN_MAX_EXTRACTED_SIZE_KB', 102400),

    'install_path' => base_path('plugins'),

    'upload_disk' => env('PLUGIN_UPLOAD_DISK', 'local'),

];
