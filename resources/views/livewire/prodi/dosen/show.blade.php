@php
    $dosen = $this->dosen;
    $namaLengkap = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));
@endphp

@section('title', 'Detail Dosen — ' . config('app.name'))
@section('header_title', 'Detail Dosen')
@section('header_subtitle', $dosen->kode_dosen ?? $namaLengkap)

<div>
    <div class="mb-4 flex flex-wrap items-center justify-end gap-2">
        <a
            href="{{ route('prodi.dosen') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <dl class="divide-y divide-neutral-100">
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Kode Dosen</dt>
                <dd class="text-sm font-semibold text-neutral-900">{{ $dosen->kode_dosen ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama</dt>
                <dd class="text-sm text-neutral-900">{{ $namaLengkap ?: '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">NIP</dt>
                <dd class="text-sm text-neutral-900">{{ $dosen->nip ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">NIDN</dt>
                <dd class="text-sm text-neutral-900">{{ $dosen->nidn ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Tempat, Tanggal Lahir</dt>
                <dd class="text-sm text-neutral-900">
                    {{ collect([$dosen->tempat_lahir, optional($dosen->tanggal_lahir)->translatedFormat('d F Y')])->filter()->implode(', ') ?: '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Jenis Kelamin</dt>
                <dd class="text-sm text-neutral-900">
                    {{ $dosen->jenis_kelamin === 'L' ? 'Laki-laki' : ($dosen->jenis_kelamin === 'P' ? 'Perempuan' : ($dosen->jenis_kelamin ?? '—')) }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">No. HP</dt>
                <dd class="text-sm text-neutral-900">{{ $dosen->no_hp ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Alamat</dt>
                <dd class="text-sm text-neutral-900">{{ $dosen->alamat ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Kode Pos</dt>
                <dd class="text-sm text-neutral-900">{{ $dosen->kode_pos ?? '—' }}</dd>
            </div>
            @if ($dosen->kota?->nama || $dosen->provinsi?->nama || $dosen->negara?->nama)
                <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Kota / Provinsi / Negara</dt>
                    <dd class="text-sm text-neutral-900">
                        {{ collect([$dosen->kota?->nama, $dosen->provinsi?->nama, $dosen->negara?->nama])->filter()->implode(' / ') ?: '—' }}
                    </dd>
                </div>
            @endif
        </dl>
    </div>
</div>
