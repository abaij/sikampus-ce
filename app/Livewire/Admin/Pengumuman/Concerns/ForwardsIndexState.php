<?php

namespace App\Livewire\Admin\Pengumuman\Concerns;

/**
 * Dipakai oleh Form. Index (lihat Index::render()) menyelipkan pencarian/filter/halaman aktif
 * ke query string link "Ubah" — trait ini membaca kembali query string tersebut (whitelist
 * eksplisit) supaya tombol Batal dan redirect setelah simpan mendarat di halaman/filter yang
 * sama, bukan selalu halaman 1 Index. Sama polanya dengan App\Livewire\Admin\Survey\Concerns\ForwardsIndexState.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'audien', 'prioritas', 'status', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.administrasi.pengumuman')
            : route('admin.administrasi.pengumuman').'?'.$this->returnQuery;
    }
}
