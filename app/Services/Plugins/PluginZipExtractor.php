<?php

namespace App\Services\Plugins;

use App\Exceptions\Plugins\PluginInstallException;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Ekstraksi ZIP plugin yang tervalidasi entry-per-entry: menolak path traversal
 * (zip-slip), path absolut, symlink, dan membatasi total ukuran hasil ekstrak
 * (guard zip-bomb) sebelum entry itu benar-benar ditulis ke disk.
 */
class PluginZipExtractor
{
    public function extract(string $zipPath, string $destinationDir, int $maxExtractedSizeKb): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new PluginInstallException('Berkas ZIP tidak dapat dibuka atau rusak.');
        }

        File::ensureDirectoryExists($destinationDir);
        $realDestination = realpath($destinationDir);

        if ($realDestination === false) {
            $zip->close();
            throw new PluginInstallException('Direktori tujuan ekstraksi tidak valid.');
        }

        $maxExtractedBytes = $maxExtractedSizeKb * 1024;
        $totalBytes = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $stat = $zip->statIndex($i);

                if ($name === false || $stat === false) {
                    throw new PluginInstallException('Entri ZIP tidak valid.');
                }

                $this->assertSafeEntryName($name);
                $this->assertNotSymlink($zip, $i);

                $totalBytes += (int) $stat['size'];
                if ($totalBytes > $maxExtractedBytes) {
                    throw new PluginInstallException('Ukuran hasil ekstrak ZIP melebihi batas maksimum yang diizinkan.');
                }

                $targetPath = $destinationDir.DIRECTORY_SEPARATOR.$name;

                if (str_ends_with($name, '/')) {
                    File::ensureDirectoryExists($targetPath);

                    continue;
                }

                File::ensureDirectoryExists(dirname($targetPath));

                if (! $zip->extractTo($destinationDir, [$name])) {
                    throw new PluginInstallException("Gagal mengekstrak entri ZIP: {$name}");
                }

                $resolvedPath = realpath($targetPath);
                if ($resolvedPath === false || ! str_starts_with($resolvedPath, $realDestination.DIRECTORY_SEPARATOR)) {
                    throw new PluginInstallException('Entri ZIP keluar dari direktori tujuan (zip-slip terdeteksi).');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function assertSafeEntryName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new PluginInstallException('Nama entri ZIP tidak valid.');
        }

        if (str_contains($name, '..')) {
            throw new PluginInstallException("Entri ZIP berisi path traversal: {$name}");
        }

        if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            throw new PluginInstallException("Entri ZIP menggunakan path absolut: {$name}");
        }

        if (preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw new PluginInstallException("Entri ZIP menggunakan drive letter Windows: {$name}");
        }

        if (str_contains($name, '\\')) {
            throw new PluginInstallException("Entri ZIP menggunakan backslash pada path: {$name}");
        }
    }

    private function assertNotSymlink(ZipArchive $zip, int $index): void
    {
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $unixMode = ($attr >> 16) & 0170000;

        if ($unixMode === 0120000) {
            throw new PluginInstallException('Entri ZIP berupa symlink, tidak diizinkan.');
        }
    }
}
