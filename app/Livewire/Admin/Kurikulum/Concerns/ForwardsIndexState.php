<?php

namespace App\Livewire\Admin\Kurikulum\Concerns;

/**
 * Sama polanya dengan App\Livewire\Admin\Matkul\Concerns\ForwardsIndexState — dipakai bersama
 * oleh Show dan Form supaya tombol Kembali/Batal dan redirect setelah simpan mendarat di
 * halaman/filter Index yang sama, bukan selalu halaman 1.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'status', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.akademik.kurikulum')
            : route('admin.akademik.kurikulum').'?'.$this->returnQuery;
    }
}
