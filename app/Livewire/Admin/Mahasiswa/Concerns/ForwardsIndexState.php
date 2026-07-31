<?php

namespace App\Livewire\Admin\Mahasiswa\Concerns;

/**
 * Dipakai bersama oleh Show dan Form. Index (lihat Index::render()) menyelipkan
 * pencarian/filter/halaman aktif ke query string link "Lihat Detail" — trait ini membaca
 * kembali query string tersebut (whitelist eksplisit) supaya tombol Kembali di Show dan
 * Batal/redirect-setelah-simpan di Form mendarat di halaman/filter yang sama, bukan selalu
 * halaman 1 Index.
 *
 * Form di modul ini tidak langsung kembali ke Index (beda dari
 * App\Livewire\Admin\Kelas\Concerns\ForwardsIndexState) — Form selalu kembali ke Show mahasiswa
 * yang sama, jadi trait ini punya dua resolver terpisah seperti
 * App\Livewire\Admin\DosenWali\Concerns\ForwardsIndexState: satu untuk Show -> Index, satu untuk
 * Form -> Show, supaya query pencarian/filter/halaman tetap mengalir lewat Show ketika user
 * selesai mengubah data dan kembali lagi.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    private function whitelistedQuery(): array
    {
        return collect(request()->query())
            ->only(['search', 'id_prodi', 'id_kelompok_kelas', 'id_semester_masuk', 'id_status_akademik', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    protected function resolveBackToIndexUrl(): void
    {
        $this->returnQuery = http_build_query($this->whitelistedQuery());

        $this->backUrl = $this->returnQuery === ''
            ? route('admin.administrasi.mahasiswa')
            : route('admin.administrasi.mahasiswa').'?'.$this->returnQuery;
    }

    protected function resolveBackToShowUrl(int $mahasiswaId): void
    {
        $this->returnQuery = http_build_query($this->whitelistedQuery());

        $this->backUrl = $this->returnQuery === ''
            ? route('admin.administrasi.mahasiswa.show', $mahasiswaId)
            : route('admin.administrasi.mahasiswa.show', $mahasiswaId).'?'.$this->returnQuery;
    }
}
