@section('title', 'Detail Mata Kuliah — ' . config('app.name'))
@section('header_title', 'Detail Mata Kuliah')
@section('header_subtitle', $matkul->kode)

<div>
    <div class="mb-4 flex flex-wrap items-center justify-end gap-2">
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <dl class="divide-y divide-neutral-100">
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Kode</dt>
                <dd class="font-mono text-sm font-semibold text-neutral-900">{{ $matkul->kode ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama (Indonesia)</dt>
                <dd class="text-sm text-neutral-900">{{ $matkul->nama ?? '—' }}</dd>
            </div>
            @if ($matkul->nama_en)
                <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama (English)</dt>
                    <dd class="text-sm text-neutral-900">{{ $matkul->nama_en }}</dd>
                </div>
            @endif
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="text-sm text-neutral-900">{{ $matkul->prodi?->nama ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Jenis Mata Kuliah</dt>
                <dd class="text-sm text-neutral-900">{{ $matkul->jenisMatkul?->nama ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">SKS</dt>
                <dd class="text-sm text-neutral-900">{{ $matkul->sks ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Semester</dt>
                <dd class="text-sm text-neutral-900">{{ $matkul->semester ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Status</dt>
                <dd class="text-sm">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $matkul->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-700' }}">
                        {{ $matkul->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                </dd>
            </div>
            @if ($matkul->deskripsi)
                <div class="py-3">
                    <dt class="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">Deskripsi</dt>
                    <dd class="whitespace-pre-wrap text-sm text-neutral-800">{{ $matkul->deskripsi }}</dd>
                </div>
            @endif
        </dl>
    </div>
</div>
