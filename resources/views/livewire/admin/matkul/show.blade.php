@section('title', 'Detail Mata Kuliah — ' . config('app.name'))
@section('header_title', 'Detail Mata Kuliah')
@section('header_subtitle', $matkul->kode)
@section('header_icon', 'book-open')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Mata Kuliah', 'route' => route('admin.akademik.matkul')],
        ['label' => $matkul->kode],
    ]])
@endsection

@section('page_actions')
    <div class="flex items-center gap-2">
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
        <a
            href="{{ route('admin.akademik.matkul.edit', $matkul->id) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
        <button
            type="button"
            wire:click="confirmDeleteMatkul"
            class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 shadow-sm transition hover:bg-rose-100"
        >
            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
            Hapus
        </button>
    </div>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="divide-y divide-slate-100">
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kode</dt>
                <dd class="font-mono text-sm font-semibold text-slate-900">{{ $matkul->kode }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</dt>
                <dd class="text-sm text-slate-900">{{ $matkul->nama }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama (English)</dt>
                <dd class="text-sm text-slate-900">{{ $matkul->nama_en ?: '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Program Studi</dt>
                <dd class="text-sm text-slate-900">
                    {{ $matkul->prodi ? $matkul->prodi->nama . ($matkul->prodi->jenjang?->kode ? " ({$matkul->prodi->jenjang->kode})" : '') : '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Mata Kuliah</dt>
                <dd class="text-sm text-slate-900">
                    {{ $matkul->jenisMatkul ? ($matkul->jenisMatkul->kode ? "{$matkul->jenisMatkul->nama} ({$matkul->jenisMatkul->kode})" : $matkul->jenisMatkul->nama) : '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">SKS</dt>
                <dd class="text-sm text-slate-900">{{ $matkul->sks ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Semester</dt>
                <dd class="text-sm text-slate-900">{{ $matkul->semester ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="text-sm">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $matkul->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                        {{ $matkul->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                </dd>
            </div>
            <div class="py-3">
                <dt class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi</dt>
                <dd class="whitespace-pre-wrap text-sm text-slate-800">{{ $matkul->deskripsi ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Mata Kuliah Prasyarat</h2>
                <p class="text-sm text-slate-500">Mahasiswa harus menyelesaikan mata kuliah berikut sebelum dapat mengambil mata kuliah ini.</p>
            </div>
            <button
                type="button"
                wire:click="openAddPrasyaratModal"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
            >
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Tambah Prasyarat
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">SKS</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->prasyaratList as $row)
                        <tr wire:key="prasyarat-{{ $row->id }}">
                            <td class="px-4 py-3 font-mono font-medium text-slate-900">{{ $row->matkulPrasyarat->kode }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $row->matkulPrasyarat->nama }}</div>
                                @if ($row->matkulPrasyarat->nama_en)
                                    <div class="text-xs text-slate-500">{{ $row->matkulPrasyarat->nama_en }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $row->matkulPrasyarat->sks ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row->matkulPrasyarat->semester ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row->matkulPrasyarat->prodi->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="openEditPrasyaratModal({{ $row->id }})"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                        title="Ubah"
                                    >
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="confirmDeletePrasyarat({{ $row->id }})"
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
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada mata kuliah prasyarat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal: Tambah/Ubah Prasyarat --}}
    @if ($showPrasyaratModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">{{ $editingPrasyaratId ? 'Ubah prasyarat' : 'Tambah prasyarat' }}</h3>
                    <button type="button" wire:click="closePrasyaratModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <form wire:submit="savePrasyarat" class="space-y-4 p-6">
                    <p class="text-sm text-slate-500">
                        Hanya mata kuliah dari program studi yang sama{{ $matkul->id_prodi === null ? ' (tanpa prodi)' : '' }}. Cari berdasarkan kode atau nama (minimal 1 karakter).
                    </p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Mata kuliah prasyarat</label>

                        @if ($selectedPrasyaratId)
                            <div class="flex items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm">
                                <span class="font-medium text-slate-900">{{ $selectedPrasyaratLabel }}</span>
                                <button type="button" wire:click="$set('selectedPrasyaratId', null)" class="text-slate-400 transition hover:text-slate-600">
                                    <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                                </button>
                            </div>
                        @else
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="prasyaratSearch"
                                    placeholder="Ketik kode atau nama mata kuliah..."
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                />
                                @if ($prasyaratSearch !== '')
                                    <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                        @forelse ($this->prasyaratSearchResults as $mk)
                                            <button
                                                type="button"
                                                wire:click="selectPrasyaratOption({{ $mk->id }}, '{{ addslashes($mk->kode . ' — ' . $mk->nama) }}')"
                                                class="block w-full px-3 py-2 text-left text-sm transition hover:bg-slate-50"
                                            >
                                                <span class="font-mono font-medium text-slate-900">{{ $mk->kode }}</span>
                                                <span class="text-slate-500"> — {{ $mk->nama }}</span>
                                            </button>
                                        @empty
                                            <p class="px-3 py-2 text-sm text-slate-500">Tidak ada hasil.</p>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('selectedPrasyaratId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
                        <button type="button" wire:click="closePrasyaratModal" class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Batal
                        </button>
                        <button type="submit" @disabled(! $selectedPrasyaratId) class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Hapus Prasyarat --}}
    @if ($confirmingDeletePrasyaratId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">Hapus prasyarat?</h3>
                <p class="mt-2 text-sm text-slate-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeletePrasyarat" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="button" wire:click="deletePrasyarat" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Hapus Mata Kuliah --}}
    @if ($confirmingDeleteMatkul)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">Hapus mata kuliah?</h3>
                <p class="mt-2 text-sm text-slate-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteMatkul" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteMatkul" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
