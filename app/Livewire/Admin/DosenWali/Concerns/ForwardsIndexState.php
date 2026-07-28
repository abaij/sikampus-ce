<?php

namespace App\Livewire\Admin\DosenWali\Concerns;

/**
 * Modul ini bertingkat tiga (Index -> Show -> Riwayat), jadi trait ini dipakai oleh dua
 * komponen dengan whitelist query yang berbeda:
 *
 * - Show memanggil resolveBackToIndexUrl() untuk kembali ke Index (daftar dosen) dengan
 *   pencarian/halaman yang sama — dibaca dari query 'search'/'page' yang diselipkan Index ke
 *   link "Lihat" (lihat Index::render()).
 * - Riwayat memanggil resolveBackToShowUrl() untuk kembali ke Show (daftar mahasiswa bimbingan
 *   dosen tsb) dengan pencarian/halaman yang sama — dibaca dari query 'mhs_search'/'mhs_page'
 *   yang diselipkan Show ke link "Riwayat bimbingan" (lihat Show::render()).
 *
 * Prefix mhs_ sengaja dipakai supaya query milik Show tidak bentrok dengan query 'search'/'page'
 * milik Index yang ikut terbawa di URL Show (Index -> Show -> Riwayat -> kembali ke Show harus
 * memulihkan state Show, bukan state Index). Sama polanya dengan
 * App\Livewire\Admin\Jadwal\Concerns\ForwardsIndexState, hanya beda tingkat.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackToIndexUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.administrasi.dosen-wali')
            : route('admin.administrasi.dosen-wali').'?'.$this->returnQuery;
    }

    protected function resolveBackToShowUrl(int $dosenId): void
    {
        $forwarded = collect(request()->query())
            ->only(['mhs_search', 'mhs_page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.administrasi.dosen-wali.show', $dosenId)
            : route('admin.administrasi.dosen-wali.show', $dosenId).'?'.$this->returnQuery;
    }
}
