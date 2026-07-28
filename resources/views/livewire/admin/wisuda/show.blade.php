@php
    $w = $this->wisuda;
    $pesertaStatusLabel = fn (?string $s) => match ($s) {
        'pending' => 'Menunggu',
        'acc' => 'Disetujui (acc)',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => $s ?: '—',
    };
    $pesertaStatusClass = fn (?string $s) => match ($s) {
        'approved', 'acc' => 'bg-emerald-50 text-emerald-700',
        'rejected' => 'bg-rose-50 text-rose-700',
        'pending' => 'bg-amber-50 text-amber-700',
        default => 'bg-neutral-100 text-neutral-600',
    };
@endphp

@section('title', 'Detail Wisuda — ' . config('app.name'))
@section('header_title', $w->nama)
@section('header_subtitle', 'Detail wisuda dan daftar peserta')
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Wisuda', 'route' => route('admin.akademik.wisuda')],
        ['label' => $w->nama],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.wisuda.edit', $w->id) }}"
        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
        Ubah Wisuda
    </a>
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Informasi Wisuda</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-xs text-neutral-500">Nama</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $w->nama }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Tanggal Wisuda</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $w->tanggal_wisuda?->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Status</p>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $w->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                    {{ $w->status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                </span>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Jumlah Mahasiswa</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $w->jumlah_mahasiswa }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="mb-1 text-xs text-neutral-500">Keterangan</p>
                <p class="whitespace-pre-wrap text-sm text-neutral-800">{{ $w->keterangan ?: '—' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 p-4">
            <h2 class="text-base font-semibold text-neutral-900">Peserta Wisuda</h2>
            {{-- Tombol wire:click harus di dalam root komponen, bukan di section page_actions --}}
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="exportPdf"
                    wire:loading.attr="disabled"
                    wire:target="exportPdf"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="exportPdf" class="inline-flex items-center gap-2">
                        <i data-lucide="file-text" class="h-4 w-4" aria-hidden="true"></i>
                        PDF
                    </span>
                    <span wire:loading wire:target="exportPdf" class="inline-flex items-center gap-2">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                        Menyiapkan...
                    </span>
                </button>
                <button
                    type="button"
                    wire:click="exportExcel"
                    wire:loading.attr="disabled"
                    wire:target="exportExcel"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="exportExcel" class="inline-flex items-center gap-2">
                        <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
                        Excel
                    </span>
                    <span wire:loading wire:target="exportExcel" class="inline-flex items-center gap-2">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                        Menyiapkan...
                    </span>
                </button>
                <button
                    type="button"
                    wire:click="openTambahModal"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah Peserta
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">No. SK</th>
                        <th class="px-4 py-3">Tgl. SK</th>
                        <th class="px-4 py-3">Berkas</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->pesertaList as $p)
                        <tr wire:key="peserta-{{ $p->id }}">
                            <td class="px-4 py-3 text-neutral-600">{{ $p->mahasiswa?->nim ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $p->mahasiswa?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $p->mahasiswa?->prodi?->nama ?? '—' }}
                                @if ($p->mahasiswa?->prodi?->jenjang?->kode)
                                    <span class="text-neutral-400">({{ $p->mahasiswa->prodi->jenjang->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $p->no_sk_wisuda ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $p->tanggal_sk_wisuda ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                <div class="flex items-center gap-2">
                                    @if ($p->file_sk_wisuda)
                                        <a href="{{ Str::startsWith($p->file_sk_wisuda, ['http://', 'https://']) ? $p->file_sk_wisuda : asset('storage/'.ltrim($p->file_sk_wisuda, '/')) }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline" title="File SK">SK</a>
                                    @endif
                                    @if ($p->foto)
                                        <a href="{{ Str::startsWith($p->foto, ['http://', 'https://']) ? $p->foto : asset('storage/'.ltrim($p->foto, '/')) }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline" title="Foto">Foto</a>
                                    @endif
                                    @if (! $p->file_sk_wisuda && ! $p->foto)
                                        —
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $pesertaStatusClass($p->status) }}">
                                    {{ $pesertaStatusLabel($p->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" wire:click="openEditPeserta({{ $p->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900" title="Ubah">
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" wire:click="confirmDeletePeserta({{ $p->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700" title="Hapus dari wisuda">
                                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Belum ada peserta terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal: tambah peserta --}}
    @if ($showTambahModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4 py-6">
            <div class="max-h-full w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Tambah Peserta Wisuda</h3>
                        <p class="mt-1 text-sm text-neutral-600">Hanya mahasiswa yang sudah memiliki data yudisium dan belum terdaftar di wisuda ini yang muncul.</p>
                    </div>
                    <button type="button" wire:click="closeTambahModal" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                @if ($selectedMahasiswaId)
                    <div class="mb-4 flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                        <span class="font-medium text-neutral-900">{{ $selectedMahasiswaLabel }}</span>
                        <button type="button" wire:click="clearMahasiswa" class="text-neutral-400 transition hover:text-neutral-600">
                            <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. SK Wisuda</label>
                            <input type="text" wire:model="no_sk_wisuda" placeholder="Opsional" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            @error('no_sk_wisuda') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal SK Wisuda</label>
                            <input type="date" wire:model="tanggal_sk_wisuda" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            @error('tanggal_sk_wisuda') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">File SK Wisuda (path / URL)</label>
                            <input type="text" wire:model="file_sk_wisuda" placeholder="Opsional" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            @error('file_sk_wisuda') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Foto (path / URL)</label>
                            <input type="text" wire:model="foto" placeholder="Opsional" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            @error('foto') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                            <x-searchable-select
                                model="pesertaStatus"
                                :clearable="false"
                                :options="$this->pesertaStatusOptions()"
                            />
                            @error('pesertaStatus') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @error('selectedMahasiswaId') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                        <button type="button" wire:click="closeTambahModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                        <button type="button" wire:click="savePeserta" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">Daftarkan</button>
                    </div>
                @else
                    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                            <x-searchable-select
                                model="calonFilterProdi"
                                :live="true"
                                :options="$this->prodiOptions"
                                optionLabel="label"
                                placeholder="Semua prodi"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-neutral-700">Cari Nama / NIM</label>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="calonSearch"
                                placeholder="Ketik nama atau NIM..."
                                class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                            />
                        </div>
                    </div>

                    @error('selectedMahasiswaId') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="overflow-x-auto rounded-lg shadow-border">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <tr>
                                    <th class="px-4 py-3">NIM</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Prodi</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @forelse ($this->calonPeserta as $m)
                                    <tr wire:key="calon-{{ $m->id }}">
                                        <td class="px-4 py-3 text-neutral-600">{{ $m->nim ?? '—' }}</td>
                                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $m->nama }}</td>
                                        <td class="px-4 py-3 text-neutral-600">{{ $m->prodi?->nama ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" wire:click="selectMahasiswa({{ $m->id }})" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-border transition hover:bg-neutral-50">
                                                Pilih
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-neutral-500">Tidak ada mahasiswa yang memenuhi syarat.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Modal: ubah peserta --}}
    @if ($editingPesertaId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4 py-6">
            <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Ubah Peserta</h3>
                        <p class="mt-1 text-sm text-neutral-600">{{ $selectedMahasiswaLabel }}</p>
                    </div>
                    <button type="button" wire:click="closeEditPeserta" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. SK Wisuda</label>
                        <input type="text" wire:model="no_sk_wisuda" placeholder="Opsional" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('no_sk_wisuda') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal SK Wisuda</label>
                        <input type="date" wire:model="tanggal_sk_wisuda" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('tanggal_sk_wisuda') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">File SK Wisuda (path / URL)</label>
                        <input type="text" wire:model="file_sk_wisuda" placeholder="Opsional" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('file_sk_wisuda') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Foto (path / URL)</label>
                        <input type="text" wire:model="foto" placeholder="Opsional" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('foto') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status Peserta</label>
                        <x-searchable-select
                            model="pesertaStatus"
                            :clearable="false"
                            :options="$this->pesertaStatusOptions()"
                        />
                        @error('pesertaStatus') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" wire:click="closeEditPeserta" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="saveEditPeserta" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: konfirmasi hapus peserta --}}
    @if ($confirmingPesertaDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus peserta wisuda?</h3>
                <p class="mt-2 text-sm text-neutral-600">Mahasiswa ini akan dikeluarkan dari daftar peserta wisuda.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeletePeserta" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="deletePeserta" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
