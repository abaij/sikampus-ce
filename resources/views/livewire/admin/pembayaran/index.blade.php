@section('title', 'Pembayaran — ' . config('app.name'))
@section('header_title', 'Pembayaran')
@section('header_subtitle', 'Kelola pembayaran tagihan mahasiswa')
@section('header_icon', 'wallet')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Pembayaran'],
    ]])
@endsection

@section('page_actions')
    @if (\App\Support\PanelAccess::can(auth()->user(), 'pembayaran', 'create'))
        <a
            href="{{ route('admin.keuangan.pembayaran.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Pembayaran
        </a>
    @endif
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
                    placeholder="Cari nomor pembayaran, nama, atau NIM mahasiswa..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Periode Tagihan</label>
                    <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua periode" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                    <x-searchable-select model="filterProdi" :options="$this->prodiOptions" :live="true" placeholder="Semua prodi" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status ACC</label>
                    <x-searchable-select model="filterAccStatus" :options="$this->accStatusOptions()" :live="true" placeholder="Semua status" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Mulai Tanggal</label>
                    <input type="date" wire:model.live="tanggalDari" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Hingga Tanggal</label>
                    <input type="date" wire:model.live="tanggalSampai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">No. Pembayaran</th>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Periode</th>
                        <th class="px-4 py-3">Nominal</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Metode</th>
                        <th class="px-4 py-3">Status ACC</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($pembayaranList as $pembayaran)
                        <tr wire:key="pembayaran-{{ $pembayaran->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $pembayaran->no_pembayaran }}</td>
                            <td class="px-4 py-3">
                                <div class="text-neutral-900">{{ $pembayaran->tagihan->mahasiswa->nama ?? '—' }}</div>
                                <div class="text-xs text-neutral-500">
                                    {{ $pembayaran->tagihan->mahasiswa->nim ?? '' }}
                                    @if ($pembayaran->tagihan?->mahasiswa?->prodi?->nama)
                                        &middot; {{ $pembayaran->tagihan->mahasiswa->prodi->nama }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $pembayaran->tagihan->semester->nama ?? '—' }}
                                @if ($pembayaran->tagihan?->semester?->kode)
                                    <span class="text-xs text-neutral-500">({{ $pembayaran->tagihan->semester->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold text-neutral-900">Rp{{ number_format((float) $pembayaran->nominal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pembayaran->tanggal_pembayaran?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pembayaran->metode_pembayaran ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($pembayaran->approved_at)
                                    <span class="inline-flex rounded-lg bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">ACC</span>
                                @else
                                    <span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900">Belum ACC</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.keuangan.pembayaran.show', $pembayaran->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Detail"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Belum ada data pembayaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $pembayaranList->links() }}
        </div>
    </div>
</div>
