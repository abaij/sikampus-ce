@section('title', 'Rentang Nilai — ' . config('app.name'))
@section('header_title', 'Rentang Nilai')
@section('header_subtitle', 'Master rentang nilai per jenjang pendidikan')
@section('header_icon', 'sliders-horizontal')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Rentang Nilai'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.rentang-nilai.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Rentang Nilai
    </a>
@endsection

@php
    $fmtNum = function ($v) {
        $n = (float) $v;
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    };
@endphp

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-end gap-3 border-b border-neutral-200 p-4">
            <div class="w-64">
                <label class="mb-1 block text-xs font-semibold text-neutral-700">Jenjang</label>
                <x-searchable-select
                    model="filterJenjang"
                    :options="$this->jenjangOptions"
                    placeholder="Semua jenjang"
                    :live="true"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Jenjang</th>
                        <th class="px-4 py-3">Huruf</th>
                        <th class="px-4 py-3">Angka Mutu</th>
                        <th class="px-4 py-3">Rentang</th>
                        <th class="px-4 py-3">Lulus</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($rentangNilaiList as $row)
                        <tr wire:key="rentang-nilai-{{ $row->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $row->jenjang->nama ?? '—' }}</div>
                                @if ($row->jenjang?->kode)
                                    <div class="text-xs text-neutral-500">{{ $row->jenjang->kode }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold text-neutral-900">{{ $row->nilai_huruf }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $fmtNum($row->nilai_angka) }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $fmtNum($row->nilai_rendah) }} – {{ $fmtNum($row->nilai_tinggi) }}</td>
                            <td class="px-4 py-3">
                                @if ($row->is_lulus !== false)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Ya</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-600">Tidak</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.akademik.rentang-nilai.edit', $row->id) }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Ubah"
                                    >
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $row->id }})"
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
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus rentang nilai?</h3>
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
