@php
    $data = $this->ujianSidang;
    $ta = $data->tugasAkhir;
    $statusBadgeClass = fn (?string $s) => match ($s) {
        'approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        'submitted' => 'bg-sky-50 text-sky-800 ring-sky-100',
        'rejected' => 'bg-rose-50 text-rose-800 ring-rose-100',
        'returned' => 'bg-amber-50 text-amber-900 ring-amber-100',
        default => 'bg-neutral-100 text-neutral-700 ring-neutral-200',
    };
    $statusLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft', 'submitted' => 'Terkirim', 'approved' => 'Disetujui',
        'rejected' => 'Ditolak', 'returned' => 'Dikembalikan', default => $s ?? '—',
    };
    $statusPengujiLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft', 'submitted' => 'Terkirim', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
        default => $s ?? '—',
    };
@endphp

@section('title', 'Detail Ujian Sidang — ' . config('app.name'))
@section('header_title', 'Detail ujian sidang')

@section('breadcrumb')
    <a href="{{ route('mahasiswa.akhir-studi.ujian-sidang') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div class="mx-auto max-w-3xl space-y-6">
    <section class="rounded-xl bg-white p-4 shadow-border">
        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Tugas akhir</p>
        <p class="mt-1 text-base font-semibold text-neutral-900">{{ $ta->judul ?? '' }}</p>
        <p class="mt-1 text-sm text-neutral-600">
            Status judul:
            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadgeClass($ta->status ?? null) }}">
                {{ $statusLabel($ta->status ?? null) }}
            </span>
        </p>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-border">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <i data-lucide="book-open" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                <span class="font-semibold text-neutral-800">
                    {{ $data->semester->nama ?? 'Ujian sidang' }}
                    @if ($data->semester?->kode)
                        <span class="font-normal text-neutral-500">({{ $data->semester->kode }})</span>
                    @endif
                </span>
            </div>
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($data->status) }}">{{ $statusLabel($data->status) }}</span>
        </div>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-xs text-neutral-500">Tanggal daftar</dt>
                <dd class="font-medium text-neutral-900">{{ $data->tanggal_daftar?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
            </div>
            <div class="flex flex-wrap gap-6">
                <div class="flex items-start gap-2">
                    <i data-lucide="calendar" class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" aria-hidden="true"></i>
                    <div>
                        <p class="text-xs text-neutral-500">Mulai ujian</p>
                        <p class="font-medium text-neutral-900">{{ $data->tanggal_ujian_mulai?->translatedFormat('d M Y H:i') ?? '—' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <i data-lucide="calendar" class="mt-0.5 h-4 w-4 shrink-0 text-sky-600" aria-hidden="true"></i>
                    <div>
                        <p class="text-xs text-neutral-500">Selesai ujian</p>
                        <p class="font-medium text-neutral-900">{{ $data->tanggal_ujian_selesai?->translatedFormat('d M Y H:i') ?? '—' }}</p>
                    </div>
                </div>
            </div>
            @if ($data->file_proposal)
                <div>
                    <dt class="text-xs text-neutral-500">Laporan tugas akhir / skripsi</dt>
                    <dd class="mt-1">
                        <a href="{{ asset('storage/'.ltrim($data->file_proposal, '/')) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 font-medium text-sky-700 underline decoration-sky-300 underline-offset-2 hover:text-sky-900">
                            <i data-lucide="download" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                            Unduh berkas
                        </a>
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-border">
        <div class="mb-3 flex items-center gap-2">
            <i data-lucide="users" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
            <h2 class="text-sm font-semibold text-neutral-800">Dosen penguji</h2>
        </div>
        @if ($data->penguji->isNotEmpty())
            <ul class="divide-y divide-neutral-100 rounded-lg shadow-border">
                @foreach ($data->penguji as $pj)
                    <li class="px-3 py-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-center gap-2">
                                <i data-lucide="user-circle" class="h-4 w-4 shrink-0 text-neutral-400" aria-hidden="true"></i>
                                <span class="font-medium text-neutral-900">
                                    {{ $pj->dosen->nama ?? '—' }}
                                    @if ($pj->dosen?->kode_dosen)
                                        <span class="ml-2 font-normal text-neutral-500">({{ $pj->dosen->kode_dosen }})</span>
                                    @endif
                                </span>
                                @if ($pj->is_ketua)
                                    <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">Ketua</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs text-neutral-600">
                                <span class="rounded bg-neutral-100 px-2 py-0.5">Nilai status: {{ $statusPengujiLabel($pj->status) }}</span>
                                @if ($pj->nilai !== null)
                                    <span class="rounded bg-neutral-100 px-2 py-0.5">Nilai: {{ $pj->nilai }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($pj->catatan)
                            <p class="mt-2 text-sm text-neutral-600">
                                <span class="font-medium text-neutral-700">Catatan: </span>{{ $pj->catatan }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-neutral-600">Penguji akan ditampilkan setelah administrasi menugaskan dosen penguji pada jadwal ini.</p>
        @endif
    </section>
</div>
