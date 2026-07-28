@section('title', 'Laporan Pelunasan Tagihan — ' . config('app.name'))
@section('header_title', 'Laporan Pelunasan Tagihan')
@section('header_subtitle', 'Total tagihan vs. pembayaran disetujui per mahasiswa')
@section('header_icon', 'file-text')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Pembayaran', 'route' => route('admin.keuangan.pembayaran')],
        ['label' => 'Laporan Pelunasan'],
    ]])
@endsection

<div>
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
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester Tagihan</label>
                    <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                    <x-searchable-select model="filterProdi" :options="$this->prodiOptions" :live="true" placeholder="Semua prodi" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 p-4">
            <p class="text-sm text-neutral-600">
                Menampilkan mahasiswa yang memiliki tagihan pada filter di atas. Total: <span class="font-semibold text-neutral-900">{{ $rows->total() }}</span> mahasiswa.
            </p>
            <button
                type="button"
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="exportExcel" class="inline-flex items-center gap-2">
                    <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
                    Ekspor Excel
                </span>
                <span wire:loading wire:target="exportExcel" class="inline-flex items-center gap-2">
                    <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                    Menyiapkan...
                </span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3 text-right">Total Tagihan</th>
                        <th class="px-4 py-3 text-right">Pembayaran Disetujui</th>
                        <th class="px-4 py-3 text-right">Sisa Tunggakan</th>
                        <th class="px-4 py-3 text-right">Pencapaian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($rows as $item)
                        <tr wire:key="pelunasan-{{ $item->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $item->nim }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $item->nama }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $item->prodi->nama ?? '—' }}
                                @if ($item->prodi?->kode)
                                    <span class="text-xs text-neutral-500">({{ $item->prodi->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-neutral-900">Rp{{ number_format($item->total_tagihan, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-emerald-700">Rp{{ number_format($item->total_pembayaran, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-sky-700">Rp{{ number_format($item->sisa, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-sky-700">{{ number_format($item->persentase, 2) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Tidak ada data untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $rows->links() }}
        </div>
    </div>
</div>
