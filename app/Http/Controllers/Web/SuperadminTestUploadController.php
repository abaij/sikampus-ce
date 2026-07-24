<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SuperadminTestUploadController extends Controller
{
    public function create(): View
    {
        return view('superadmin.test-upload');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'upload_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'upload_file.required' => 'Silakan pilih file yang akan diunggah.',
            'upload_file.file' => 'Berkas yang dipilih tidak valid.',
            'upload_file.mimes' => 'Format file harus pdf, jpg, jpeg, atau png.',
            'upload_file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $file = $validated['upload_file'];
        $storedPath = $file->store('test-uploads', 'public');

        if ($storedPath === false) {
            return redirect()
                ->route('superadmin.test-upload')
                ->with('error', 'Upload gagal disimpan ke storage. Periksa izin direktori `storage/app/public`.');
        }

        $sizeInKb = (int) ceil($file->getSize() / 1024);
        $publicPath = 'storage/app/public/'.$storedPath;
        $statusMessage = sprintf(
            'Upload berhasil: %s (%s, %d KB) tersimpan di %s.',
            $file->getClientOriginalName(),
            strtoupper($file->getClientOriginalExtension()),
            $sizeInKb,
            $publicPath
        );

        if (is_link(public_path('storage')) || is_dir(public_path('storage'))) {
            $statusMessage .= ' URL file: '.Storage::disk('public')->url($storedPath).'.';
        }

        return redirect()
            ->route('superadmin.test-upload')
            ->with('status', $statusMessage);
    }
}
