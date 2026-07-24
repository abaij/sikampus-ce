<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Partner API (Aplikasi Eksternal - mis. Siska)
    |--------------------------------------------------------------------------
    |
    | API key untuk aplikasi partner yang mengonsumsi data dari siak-backend
    | (mahasiswa, prodi, dll.) tanpa autentikasi Sanctum.
    | Pisahkan beberapa key dengan koma jika ada banyak aplikasi partner.
    |
    */

    'api_keys' => array_filter(array_map('trim', explode(',', env('PARTNER_API_KEYS', '')))),

    'header_name' => env('PARTNER_API_KEY_HEADER', 'X-API-Key'),

];
