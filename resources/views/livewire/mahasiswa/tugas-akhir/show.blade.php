@php
    $data = $this->tugasAkhir;
@endphp

@section('title', 'Detail Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Detail pengajuan')

@section('breadcrumb')
    <a href="{{ route('mahasiswa.akhir-studi.tugas-akhir') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

@if ($this->canEdit)
    @section('page_actions')
        <a
            href="{{ route('mahasiswa.akhir-studi.tugas-akhir.pengajuan') }}"
            class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-900 transition hover:bg-sky-100"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah pengajuan
        </a>
    @endsection
@endif

@php
    $statusBadgeClass = match ($data->status) {
        'approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        'submitted' => 'bg-sky-50 text-sky-800 ring-sky-100',
        'rejected' => 'bg-rose-50 text-rose-800 ring-rose-100',
        'returned' => 'bg-amber-50 text-amber-900 ring-amber-100',
        default => 'bg-neutral-100 text-neutral-700 ring-neutral-200',
    };
    $statusLabel = match ($data->status) {
        'draft' => 'Draft', 'submitted' => 'Terkirim', 'approved' => 'Disetujui',
        'rejected' => 'Ditolak', 'returned' => 'Dikembalikan', default => $data->status,
    };
    $keputusanLabel = fn (string $k) => match ($k) {
        'acc' => 'Disetujui', 'returned' => 'Dikembalikan', 'declined' => 'Ditolak', default => $k,
    };
@endphp

<div class="space-y-6">
    <section class="rounded-xl bg-white p-4 shadow-border">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
            <span class="text-sm text-neutral-600">{{ $data->is_proposal !== false ? 'Proposal' : 'Final' }}</span>
            @if ($data->semester)
                <span class="text-sm text-neutral-600">· {{ $data->semester->nama }}{{ $data->semester->kode ? " ({$data->semester->kode})" : '' }}</span>
            @endif
        </div>

        <dl class="space-y-4">
            <div>
                <dt class="text-xs font-medium text-neutral-500">Judul</dt>
                <dd class="mt-1 whitespace-pre-wrap text-sm font-semibold text-neutral-900">{{ $data->judul }}</dd>
            </div>
            @if ($data->judul_en)
                <div>
                    <dt class="text-xs font-medium text-neutral-500">Judul (English)</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-neutral-800">{{ $data->judul_en }}</dd>
                </div>
            @endif
            @if ($data->topik)
                <div>
                    <dt class="text-xs font-medium text-neutral-500">Topik</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-neutral-800">{{ $data->topik }}</dd>
                </div>
            @endif
            @if ($data->topik_en)
                <div>
                    <dt class="text-xs font-medium text-neutral-500">Topik (English)</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-neutral-800">{{ $data->topik_en }}</dd>
                </div>
            @endif
            @if ($data->deskripsi)
                <div>
                    <dt class="text-xs font-medium text-neutral-500">Deskripsi</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-neutral-800">{{ $data->deskripsi }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-medium text-neutral-500">Berkas</dt>
                <dd class="mt-1">
                    @if ($data->file)
                        <a href="{{ asset('storage/'.ltrim($data->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-sky-600 hover:underline">
                            Buka / unduh berkas
                        </a>
                    @else
                        <span class="text-sm text-neutral-600">—</span>
                    @endif
                </dd>
            </div>
            <div class="flex flex-wrap gap-6 border-t border-neutral-100 pt-4 text-xs text-neutral-500">
                <span>Diperbarui: {{ $data->updated_at?->translatedFormat('d M Y H:i') ?? '—' }}</span>
                <span>Dibuat: {{ $data->created_at?->translatedFormat('d M Y H:i') ?? '—' }}</span>
            </div>
        </dl>
    </section>

    @if ($data->pembimbing->isNotEmpty())
        <section class="rounded-xl bg-white p-4 shadow-border">
            <div class="mb-3 flex items-center gap-2">
                <i data-lucide="users" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                <h2 class="text-sm font-semibold text-neutral-800">Pembimbing</h2>
            </div>
            <ul class="space-y-2">
                @foreach ($data->pembimbing as $p)
                    <li class="text-sm text-neutral-800">
                        <span class="font-medium">{{ $p->dosen->nama ?? '—' }}</span>
                        @if ($p->dosen?->kode_dosen)
                            <span class="ml-2 text-neutral-500">({{ $p->dosen->kode_dosen }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($data->statusLogs->isNotEmpty())
        <section class="rounded-xl bg-white p-4 shadow-border">
            <h2 class="mb-3 text-sm font-semibold text-neutral-800">Riwayat keputusan</h2>
            <div class="overflow-x-auto rounded-lg shadow-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-3 py-2">Waktu</th>
                            <th class="px-3 py-2">Keputusan</th>
                            <th class="px-3 py-2">Oleh</th>
                            <th class="px-3 py-2">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($data->statusLogs as $log)
                            <tr wire:key="log-{{ $log->id }}">
                                <td class="whitespace-nowrap px-3 py-2 text-neutral-700">{{ $log->created_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="px-3 py-2 font-medium text-neutral-900">{{ $keputusanLabel($log->status) }}</td>
                                <td class="px-3 py-2 text-neutral-600">{{ $log->user->name ?? $log->user->email ?? '—' }}</td>
                                <td class="max-w-xs px-3 py-2 text-neutral-600">
                                    @if ($log->keterangan)
                                        <span class="whitespace-pre-wrap">{{ $log->keterangan }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
