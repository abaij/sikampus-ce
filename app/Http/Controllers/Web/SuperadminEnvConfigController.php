<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SuperadminEnvConfigController extends Controller
{
    public function edit(): View
    {
        $path = base_path('.env');
        $exists = File::exists($path);
        $content = $exists ? File::get($path) : '';
        $writable = $exists ? File::isWritable($path) : is_writable(dirname($path));

        return view('superadmin.konfigurasi', [
            'envContent' => $content,
            'envExists' => $exists,
            'envWritable' => $writable,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'env_content' => ['nullable', 'string', 'max:1048576'],
        ]);

        $path = base_path('.env');
        $content = $validated['env_content'] ?? '';

        if (File::exists($path) && ! File::isWritable($path)) {
            return redirect()
                ->route('superadmin.konfigurasi')
                ->with('error', 'File .env tidak dapat ditulis. Periksa izin berkas di server.');
        }

        if (! File::exists($path) && ! is_writable(dirname($path))) {
            return redirect()
                ->route('superadmin.konfigurasi')
                ->with('error', 'Direktori proyek tidak dapat ditulis; tidak dapat membuat .env.');
        }

        try {
            File::put($path, $content);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('superadmin.konfigurasi')
                ->with('error', 'Gagal menyimpan .env: '.$e->getMessage());
        }

        return redirect()
            ->route('superadmin.konfigurasi')
            ->with('status', 'Berhasil menyimpan file .env. Jika mengubah variabel konfigurasi, jalankan `php artisan config:clear` di server bila perlu.');
    }
}
