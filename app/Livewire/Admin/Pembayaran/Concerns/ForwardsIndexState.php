<?php

namespace App\Livewire\Admin\Pembayaran\Concerns;

/**
 * Dipakai bersama oleh Show dan Form. Index (lihat Index::render()) menyelipkan
 * pencarian/filter/halaman aktif ke query string link "Lihat"/"Ubah" — trait ini membaca
 * kembali query string tersebut (whitelist eksplisit) supaya tombol Kembali/Batal dan redirect
 * setelah simpan mendarat di halaman/filter yang sama, bukan selalu halaman 1 Index.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'id_semester', 'id_prodi', 'acc_status', 'tanggal_pembayaran_dari', 'tanggal_pembayaran_sampai', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.keuangan.pembayaran')
            : route('admin.keuangan.pembayaran').'?'.$this->returnQuery;
    }
}
