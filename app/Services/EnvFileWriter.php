<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Baca/tulis baris KEY=VALUE tunggal di file .env tanpa menyentuh baris lain — beda dari
 * SuperadminEnvConfigController yang menimpa seluruh isi file lewat textarea bebas.
 *
 * Path bisa dioverride lewat constructor supaya bisa diuji tanpa menyentuh .env proyek yang asli
 * (lihat tests/Feature/Livewire/Admin/Sistem/PengaturanSmtpTest.php — di sana instance-nya
 * di-bind ke container dengan path file sementara).
 */
class EnvFileWriter
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    public function exists(): bool
    {
        return File::exists($this->path);
    }

    public function isWritable(): bool
    {
        return $this->exists() ? File::isWritable($this->path) : is_writable(dirname($this->path));
    }

    /**
     * Nilai sebuah key, sudah di-unquote. Token telanjang "null" (tanpa kutip) dibaca sebagai
     * string kosong — ini konvensi dotenv/Laravel yang sama dipakai helper env() bawaan
     * (lihat MAIL_SCHEME=null di .env proyek ini dan MAIL_USERNAME=null/MAIL_PASSWORD=null di
     * .env.example). Tanpa penanganan ini, form akan menampilkan literal "null" dan validasi
     * enum seperti encryption bisa gagal padahal user tidak menyentuh field itu sama sekali.
     */
    public function get(string $key): string
    {
        $content = $this->content();

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $matches)) {
            $raw = rtrim($matches[1], "\r");

            if (trim($raw) === 'null') {
                return '';
            }

            return $this->unquote($raw);
        }

        return '';
    }

    public function content(): string
    {
        return $this->exists() ? File::get($this->path) : '';
    }

    /**
     * Timpa (atau tambahkan kalau belum ada) beberapa baris KEY=VALUE sekaligus dalam satu
     * penulisan file. Baris lain di .env tidak disentuh.
     *
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $content = $this->content();

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        File::put($this->path, $content);
    }

    /**
     * Kutip nilai kalau mengandung karakter di luar token sederhana (alnum/underscore/titik/strip)
     * — sama seperti gaya baris yang sudah ada di .env ini (mis. MAIL_USERNAME="siak@sikampus.com"
     * dikutip karena ada "@", MAIL_PORT=465 tidak karena cuma digit).
     */
    private function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.\-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function unquote(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        if (str_starts_with($raw, '"') && str_ends_with($raw, '"') && strlen($raw) >= 2) {
            $inner = substr($raw, 1, -1);

            return str_replace(['\\"', '\\\\'], ['"', '\\'], $inner);
        }

        if (str_starts_with($raw, "'") && str_ends_with($raw, "'") && strlen($raw) >= 2) {
            return substr($raw, 1, -1);
        }

        return $raw;
    }
}
