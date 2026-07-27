<?php

namespace App\Livewire\Admin\Perkuliahan\Concerns;

/**
 * Dipakai oleh Show. Index (lihat Index::render()) menyelipkan pencarian/filter/halaman aktif
 * ke query string link "Kehadiran" — trait ini membaca kembali query string tersebut (whitelist
 * eksplisit) supaya tombol Kembali mendarat di halaman/filter yang sama, bukan selalu halaman 1
 * Index. Sama polanya dengan App\Livewire\Admin\Jadwal\Concerns\ForwardsIndexState.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'id_semester', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.akademik.perkuliahan')
            : route('admin.akademik.perkuliahan').'?'.$this->returnQuery;
    }
}
