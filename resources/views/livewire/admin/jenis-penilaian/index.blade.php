@section('title', 'Jenis Penilaian — ' . config('app.name'))
@section('header_title', 'Jenis Penilaian')
@section('header_subtitle', 'Data master jenis penilaian dan bobotnya')
@section('header_icon', 'clipboard-check')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Jenis Penilaian'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.jenis-penilaian.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Jenis Penilaian
    </a>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-4 flex gap-3 rounded-lg border px-4 py-3 text-sm {{ $totalBobot > 100 ? 'border-rose-100 bg-rose-50 text-rose-800' : 'border-neutral-200 bg-neutral-50 text-neutral-700' }}">
        <i data-lucide="percent" class="h-5 w-5 shrink-0" aria-hidden="true"></i>
        <span>Total bobot seluruh jenis penilaian saat ini: <strong>{{ $totalBobot }}%</strong> dari maksimal 100%.</span>
    </div>

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
                        <th class="px-4 py-3">Bobot</th>
                        <th class="px-4 py-3">Metode Penilaian</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($jenisPenilaianList as $jenisPenilaian)
                        <tr wire:key="jenis-penilaian-{{ $jenisPenilaian->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $jenisPenilaian->kode }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $jenisPenilaian->nama }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $jenisPenilaian->bobot }}%</td>
                            <td class="px-4 py-3">
                                @if ($jenisPenilaian->status === 'manual')
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Manual</span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-700">Otomatis</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.akademik.jenis-penilaian.edit', $jenisPenilaian->id) }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Ubah"
                                    >
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $jenisPenilaian->id }})"
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
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Belum ada data jenis penilaian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $jenisPenilaianList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus jenis penilaian?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelDelete"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="delete"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
