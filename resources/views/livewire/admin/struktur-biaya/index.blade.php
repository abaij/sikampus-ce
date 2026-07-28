@section('title', 'Struktur Biaya — ' . config('app.name'))
@section('header_title', 'Struktur Biaya')
@section('header_subtitle', 'Kelola nominal biaya per kategori, prodi, angkatan, dan periode')
@section('header_icon', 'layout-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Struktur Biaya'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.keuangan.struktur-biaya.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Struktur Biaya
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
                    placeholder="Cari kategori biaya, prodi, angkatan, periode, atau komponen..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kategori Biaya</label>
                    <x-searchable-select
                        model="filterKategoriBiaya"
                        :options="$this->kategoriBiayaOptions"
                        :live="true"
                        placeholder="Semua kategori"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                    <x-searchable-select
                        model="filterProdi"
                        :options="$this->prodiOptions"
                        :live="true"
                        placeholder="Semua prodi"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Angkatan</label>
                    <x-searchable-select
                        model="filterAngkatan"
                        :options="$this->semesterOptions"
                        :live="true"
                        placeholder="Semua angkatan"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Periode Berlaku</label>
                    <x-searchable-select
                        model="filterPeriode"
                        :options="$this->semesterOptions"
                        :live="true"
                        placeholder="Semua periode"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Komponen Biaya</label>
                    <x-searchable-select
                        model="filterKomponenBiaya"
                        :options="$this->komponenBiayaOptions"
                        :live="true"
                        placeholder="Semua komponen"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kategori Biaya</th>
                        <th class="px-4 py-3">Program Studi</th>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Periode Berlaku</th>
                        <th class="px-4 py-3">Komponen</th>
                        <th class="px-4 py-3">Tahap</th>
                        <th class="px-4 py-3">Nominal</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($strukturBiayaList as $strukturBiaya)
                        <tr wire:key="struktur-biaya-{{ $strukturBiaya->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $strukturBiaya->kategoriBiaya->nama ?? '—' }}</div>
                                @if ($strukturBiaya->kategoriBiaya?->kode)
                                    <div class="text-xs text-neutral-500">{{ $strukturBiaya->kategoriBiaya->kode }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $strukturBiaya->prodi->nama ?? '—' }}</div>
                                @if ($strukturBiaya->prodi?->kode)
                                    <div class="text-xs text-neutral-500">{{ $strukturBiaya->prodi->kode }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $strukturBiaya->angkatan->nama ?? '—' }}
                                @if ($strukturBiaya->angkatan?->kode)
                                    <span class="text-xs text-neutral-500">({{ $strukturBiaya->angkatan->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $strukturBiaya->periode->nama ?? '—' }}
                                @if ($strukturBiaya->periode?->kode)
                                    <span class="text-xs text-neutral-500">({{ $strukturBiaya->periode->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $strukturBiaya->komponenBiaya->nama ?? '—' }}
                                @if ($strukturBiaya->komponenBiaya?->kode)
                                    <span class="text-xs text-neutral-500">({{ $strukturBiaya->komponenBiaya->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $strukturBiaya->tahap ?? 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-neutral-900">Rp{{ number_format((float) $strukturBiaya->nominal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.keuangan.struktur-biaya.edit', $strukturBiaya->id) }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Ubah"
                                    >
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $strukturBiaya->id }})"
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
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Belum ada data struktur biaya.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $strukturBiayaList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus struktur biaya?</h3>
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
