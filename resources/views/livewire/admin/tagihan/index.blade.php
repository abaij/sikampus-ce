@php
    $statusBadge = function (string $status) {
        return match ($status) {
            'lunas' => ['Lunas', 'bg-emerald-100 text-emerald-700'],
            'dibayar_sebagian' => ['Dibayar sebagian', 'bg-sky-100 text-sky-800'],
            'kedaluwarsa' => ['Kedaluwarsa', 'bg-amber-100 text-amber-800'],
            default => ['Belum bayar', 'bg-rose-100 text-rose-700'],
        };
    };
@endphp

@section('title', 'Tagihan — ' . config('app.name'))
@section('header_title', 'Tagihan')
@section('header_subtitle', 'Kelola tagihan mahasiswa per semester')
@section('header_icon', 'receipt')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Tagihan'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.keuangan.tagihan.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Tagihan
    </a>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-3 border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama atau NIM mahasiswa..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                    <x-searchable-select model="filterProdi" :options="$this->prodiOptions" :live="true" placeholder="Semua prodi" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                    <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status Pembayaran (ACC)</label>
                    <x-searchable-select model="filterStatusPembayaranAcc" :options="$this->statusPembayaranAccOptions()" :live="true" placeholder="Semua status" />
                </div>
            </div>
            <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-800 shadow-border">
                <input type="checkbox" wire:model.live="filterLewatJatuhTempo" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                <span class="font-medium">Hanya tagihan yang sudah lewat jatuh tempo</span>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">No Tagihan</th>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Terbayar (ACC)</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Tanggal Tagihan</th>
                        <th class="px-4 py-3">Jatuh Tempo</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($tagihanList as $tagihan)
                        @php
                            $summary = $paymentSummaries[$tagihan->id];
                            [$statusLabel, $statusClass] = $statusBadge($summary['status']);
                        @endphp
                        <tr wire:key="tagihan-{{ $tagihan->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $tagihan->no_tagihan ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="text-neutral-900">{{ $tagihan->mahasiswa->nama ?? '—' }}</div>
                                <div class="text-xs text-neutral-500">
                                    {{ $tagihan->mahasiswa->nim ?? '' }}
                                    @if ($tagihan->mahasiswa?->prodi?->nama)
                                        &middot; {{ $tagihan->mahasiswa->prodi->nama }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $tagihan->semester->nama ?? '—' }}
                                @if ($tagihan->semester?->kode)
                                    <span class="text-xs text-neutral-500">({{ $tagihan->semester->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold text-neutral-900">Rp{{ number_format((float) $tagihan->total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-neutral-800">Rp{{ number_format($summary['total_disetujui'], 0, ',', '.') }}</div>
                                @if ($summary['sisa'] > 0)
                                    <div class="text-xs text-neutral-500">Sisa Rp{{ number_format($summary['sisa'], 0, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $tagihan->tanggal_tagihan?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $tagihan->tanggal_jatuh_tempo?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.keuangan.tagihan.show', $tagihan->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-sky-600 transition hover:bg-sky-50"
                                        title="Lihat Detail"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <a
                                        href="{{ route('admin.keuangan.tagihan.edit', $tagihan->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Ubah"
                                    >
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $tagihan->id }})"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                        title="Hapus"
                                    >
                                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-neutral-500">Belum ada data tagihan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $tagihanList->links() }}
        </div>
    </div>

    {{-- Modal: Konfirmasi Hapus --}}
    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus tagihan?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="delete" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
