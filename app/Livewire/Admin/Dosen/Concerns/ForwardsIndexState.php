<?php

namespace App\Livewire\Admin\Dosen\Concerns;

/**
 * Dipakai bersama oleh Show dan Form. Index (lihat Index::render()) menyelipkan
 * pencarian/halaman aktif ke query string link "Lihat"/"Ubah" — trait ini membaca kembali query
 * string tersebut (whitelist eksplisit) supaya tombol Kembali/Batal dan redirect setelah simpan
 * mendarat di halaman/pencarian yang sama, bukan selalu halaman 1 Index. Sama polanya dengan
 * App\Livewire\Admin\Kelas\Concerns\ForwardsIndexState.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.administrasi.dosen')
            : route('admin.administrasi.dosen').'?'.$this->returnQuery;
    }
}
