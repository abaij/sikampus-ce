@section('title', 'Komponen Biaya — ' . config('app.name'))
@section('header_title', 'Komponen Biaya')
@section('header_subtitle', 'Kelola komponen biaya yang dipakai pada struktur biaya')
@section('header_icon', 'layers-3')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Komponen Biaya'],
    ]])
@endsection

@section('page_actions')
    @if (\App\Support\PanelAccess::can(auth()->user(), 'komponen biaya', 'create'))
        <a
            href="{{ route('admin.keuangan.komponen-biaya.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Komponen Biaya
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
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-200 p-4">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari kode atau nama..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Per Semester</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($komponenBiayaList as $komponenBiaya)
                        <tr wire:key="komponen-biaya-{{ $komponenBiaya->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $komponenBiaya->kode }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $komponenBiaya->nama }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $komponenBiaya->is_per_semester ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-700' }}">
                                    {{ $komponenBiaya->is_per_semester ? 'Ya' : 'Tidak' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $komponenBiaya->is_akademik ? 'bg-sky-100 text-sky-700' : 'bg-neutral-100 text-neutral-700' }}">
                                    {{ $komponenBiaya->is_akademik ? 'Akademik' : 'Non Akademik' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'komponen biaya', 'update'))
                                        <a
                                            href="{{ route('admin.keuangan.komponen-biaya.edit', $komponenBiaya->id) }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'komponen biaya', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $komponenBiaya->id }})"
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
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Belum ada data komponen biaya.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $komponenBiayaList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus komponen biaya?</h3>
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
