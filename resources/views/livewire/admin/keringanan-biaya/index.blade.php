@section('title', 'Keringanan Biaya — ' . config('app.name'))
@section('header_title', 'Keringanan Biaya')
@section('header_subtitle', 'Kelola pengajuan keringanan biaya mahasiswa')
@section('header_icon', 'hand-coins')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Keringanan Biaya'],
    ]])
@endsection

@section('page_actions')
    @if (\App\Support\PanelAccess::can(auth()->user(), 'keringanan biaya', 'create'))
        <a
            href="{{ route('admin.keuangan.keringanan-biaya.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Pengajuan
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
                    placeholder="Cari nama, NIM mahasiswa, atau keterangan..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:max-w-xs">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status</label>
                    <x-searchable-select model="filterStatus" :options="$this->statusOptions()" :live="true" placeholder="Semua status" />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($keringananBiayaList as $item)
                        <tr wire:key="keringanan-biaya-{{ $item->id }}">
                            <td class="px-4 py-3">
                                <div class="text-neutral-900">{{ $item->mahasiswa->nama ?? '—' }}</div>
                                <div class="text-xs text-neutral-500">
                                    {{ $item->mahasiswa->nim ?? '' }}
                                    @if ($item->mahasiswa?->prodi?->nama)
                                        &middot; {{ $item->mahasiswa->prodi->nama }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $item->semester->nama ?? '—' }}
                                @if ($item->semester?->kode)
                                    <span class="text-xs text-neutral-500">({{ $item->semester->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $item->jenisKeringananBiaya->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-neutral-900">Rp{{ number_format((float) $item->nominal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                @if ($item->status === 'approved')
                                    <span class="inline-flex rounded-lg bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Disetujui</span>
                                @elseif ($item->status === 'rejected')
                                    <span class="inline-flex rounded-lg bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-800">Ditolak</span>
                                @else
                                    <span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900">Menunggu</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if ($item->file_lampiran)
                                        <a
                                            href="{{ $item->file_lampiran_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Lihat lampiran"
                                        >
                                            <i data-lucide="paperclip" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'keringanan biaya', 'update'))
                                        <a
                                            href="{{ route('admin.keuangan.keringanan-biaya.edit', $item->id) }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'keringanan biaya', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $item->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                            title="Hapus"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada pengajuan keringanan biaya.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $keringananBiayaList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus pengajuan keringanan biaya?</h3>
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
