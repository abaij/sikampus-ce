<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentInfoController extends Controller
{
    public const KEY = 'pmb_payment_info';

    /**
     * Info pembayaran untuk calon mahasiswa (publik) dan admin.
     */
    public function show(): JsonResponse
    {
        $setting = Setting::query()->where('key', self::KEY)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'value' => $setting?->value ?? '',
            ],
        ]);
    }

    /**
     * Simpan / perbarui teks info pembayaran (tabel settings).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string'],
        ]);

        Setting::updateOrCreate(
            ['key' => self::KEY],
            [
                'value' => $validated['value'],
                'description' => 'Informasi pembayaran PMB untuk calon mahasiswa',
                'order' => 0,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Info pembayaran berhasil disimpan',
        ]);
    }
}
