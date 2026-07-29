@section('title', 'Detail Kurikulum — ' . config('app.name'))
@section('header_title', 'Detail Kurikulum')
@section('header_subtitle', $kurikulum->kode)

{{-- Tombol aksi sengaja berada di dalam badan komponen, bukan di section page_actions: sama
     alasannya dengan Admin\Kurikulum\Show — layouts.prodi me-render page_actions di luar root
     <div> komponen, sehingga wire:click di sana tidak pernah terikat Livewire. --}}
<div>
    <div class="mb-4 flex flex-wrap items-center justify-end gap-2">
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <dl class="divide-y divide-neutral-100">
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Kode</dt>
                <dd class="font-mono text-sm font-semibold text-neutral-900">{{ $kurikulum->kode }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama</dt>
                <dd class="text-sm text-neutral-900">{{ $kurikulum->nama }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="text-sm text-neutral-900">
                    {{ $kurikulum->prodi ? $kurikulum->prodi->nama . ($kurikulum->prodi->jenjang?->kode ? " ({$kurikulum->prodi->jenjang->kode})" : '') : '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Semester Berlaku</dt>
                <dd class="text-sm text-neutral-900">
                    {{ $kurikulum->tahunBerlaku ? "{$kurikulum->tahunBerlaku->nama} ({$kurikulum->tahunBerlaku->kode})" : '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">SKS Wajib Minimal</dt>
                <dd class="text-sm text-neutral-900">{{ $kurikulum->sks_wajib_minimal ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Total SKS Mata Kuliah</dt>
                <dd class="text-sm text-neutral-900">{{ $this->totalSksKurikulum }}</dd>
            </div>
            <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Status</dt>
                <dd class="text-sm">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $kurikulum->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-700' }}">
                        {{ $kurikulum->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                </dd>
            </div>
            <div class="py-3">
                <dt class="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">Deskripsi</dt>
                <dd class="whitespace-pre-wrap text-sm text-neutral-800">{{ $kurikulum->deskripsi ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-base font-semibold text-neutral-900">
                Mata Kuliah ({{ $kurikulum->matkuls->count() }}{{ $matkulSearch !== '' ? ' · filter: '.$this->matkulList->total() : '' }})
            </h2>
            @if ($kurikulum->matkuls->count() > 0)
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                        <input
                            type="search"
                            wire:model.live.debounce.400ms="matkulSearch"
                            placeholder="Cari kode atau nama mata kuliah..."
                            class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border sm:w-72"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-600">
                        <span class="hidden sm:inline">Per halaman</span>
                        <select wire:model.live="matkulPerPage" class="rounded-lg px-2 py-1.5 text-sm font-medium text-neutral-800 shadow-border">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </label>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl shadow-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">SKS</th>
                        <th class="px-4 py-3">Semester Rekomendasi</th>
                        <th class="px-4 py-3">Wajib</th>
                        <th class="px-4 py-3">Status Bobot</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->matkulList as $matkul)
                        @php
                            $totalBobotRow = (float) ($this->matkulBobotTotals[$matkul->pivot->id] ?? 0);
                            $bobotLengkap = $totalBobotRow >= 100;
                        @endphp
                        <tr wire:key="show-matkul-{{ $matkul->id }}">
                            <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $matkul->pivot->kode_matkul ?: $matkul->kode }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $matkul->pivot->nama_matkul ?: $matkul->nama }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $matkul->pivot->sks ?? $matkul->sks ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $matkul->pivot->semester_rekomendasi ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $matkul->pivot->is_wajib ? 'bg-sky-100 text-sky-800' : 'bg-neutral-100 text-neutral-600' }}">
                                    {{ $matkul->pivot->is_wajib ? 'Wajib' : 'Pilihan' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $bobotLengkap ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}"
                                    title="Total bobot nilai {{ number_format($totalBobotRow, 0) }}%"
                                >
                                    {{ $bobotLengkap ? 'Lengkap' : 'Belum lengkap' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="openDetailModal({{ $matkul->pivot->id }})"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Detail & bobot nilai"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-neutral-500">
                                {{ $matkulSearch !== '' ? 'Tidak ada mata kuliah yang cocok dengan pencarian.' : 'Belum ada mata kuliah dalam kurikulum ini.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->matkulList->hasPages())
            <div class="mt-4 border-t border-neutral-200 pt-4">
                {{ $this->matkulList->links() }}
            </div>
        @endif
    </div>

    {{-- Modal: Detail Mata Kuliah Kurikulum + Bobot Penilaian --}}
    @if ($this->detailMatkul)
        @php
            $km = $this->detailMatkul;
            $matkulMaster = $km->matkul;
            $displayKode = $km->kode_matkul ?: $matkulMaster?->kode;
            $displayNama = $km->nama_matkul ?: $matkulMaster?->nama;
            $displayNamaEn = $km->nama_matkul_en ?: $matkulMaster?->nama_en;
            $displaySks = $km->sks ?? $matkulMaster?->sks;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4 py-8">
            <div class="flex max-h-full w-full max-w-3xl flex-col rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-6 py-4">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-neutral-900">{{ $displayNama ?: '—' }}</h3>
                        <p class="font-mono text-sm text-neutral-500">{{ $displayKode ?: '—' }}</p>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <dl class="divide-y divide-neutral-100">
                        <div class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Kode Mata Kuliah</dt>
                            <dd class="font-mono text-sm text-neutral-900">{{ $displayKode ?: '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama Mata Kuliah</dt>
                            <dd class="text-sm text-neutral-900">{{ $displayNama ?: '—' }}</dd>
                        </div>
                        @if ($displayNamaEn)
                            <div class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama (English)</dt>
                                <dd class="text-sm text-neutral-900">{{ $displayNamaEn }}</dd>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">SKS</dt>
                            <dd class="text-sm text-neutral-900">{{ $displaySks ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Semester Rekomendasi</dt>
                            <dd class="text-sm text-neutral-900">{{ $km->semester_rekomendasi ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[minmax(0,200px)_1fr] sm:gap-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Wajib</dt>
                            <dd class="text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $km->is_wajib ? 'bg-sky-100 text-sky-800' : 'bg-neutral-100 text-neutral-600' }}">
                                    {{ $km->is_wajib ? 'Wajib' : 'Pilihan' }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 border-t border-neutral-200 pt-4">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold text-neutral-900">Bobot Penilaian</h4>
                                <p class="text-xs text-neutral-500">Komponen penilaian mata kuliah ini dalam kurikulum.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="openBobotForm"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 shadow-border"
                                    title="Kelola bobot penilaian"
                                >
                                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                                    {{ $km->bobotPenilaian->count() ? 'Kelola Bobot Penilaian' : 'Tambah Bobot Penilaian' }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="openAutoFillConfirm"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 shadow-border"
                                    title="Isi bobot penilaian dari default jenis penilaian"
                                >
                                    <i data-lucide="sparkles" class="h-4 w-4" aria-hidden="true"></i>
                                    Auto Fill
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl shadow-border">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Jenis Penilaian</th>
                                        <th class="px-4 py-2.5">Kode</th>
                                        <th class="px-4 py-2.5 text-right">Bobot (%)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    @forelse ($km->bobotPenilaian as $bobotRow)
                                        <tr wire:key="bobot-{{ $bobotRow->id }}">
                                            <td class="px-4 py-2.5 font-medium text-neutral-900">{{ $bobotRow->jenisPenilaian?->nama ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-neutral-600">{{ $bobotRow->jenisPenilaian?->kode ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-neutral-800">{{ rtrim(rtrim(number_format((float) $bobotRow->bobot, 2), '0'), '.') }}%</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-6 text-center text-neutral-500">Belum ada bobot penilaian untuk mata kuliah ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Kelola Bobot Penilaian (dalam modal detail) --}}
    @if ($showBobotForm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-900/50 px-4 py-8">
            <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Kelola Bobot Penilaian</h3>
                    <button type="button" wire:click="closeBobotForm" class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>
                <form wire:submit="saveBobotForm" class="flex flex-1 flex-col overflow-hidden">
                    <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                        @error('bobotForm')
                            <p class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 ring-1 ring-rose-100">{{ $message }}</p>
                        @enderror

                        @if ($this->jenisPenilaianOptions->isEmpty())
                            <p class="py-6 text-center text-sm text-neutral-500">Tidak ada jenis penilaian.</p>
                        @else
                            <p class="mb-4 text-sm text-neutral-600">Isi bobot (%) untuk setiap komponen. Total maksimal 100%.</p>
                            <ul class="space-y-3">
                                @foreach ($this->jenisPenilaianOptions as $jenis)
                                    <li class="flex items-center gap-3">
                                        <label class="min-w-0 flex-1 text-sm font-medium text-neutral-700">
                                            {{ $jenis->nama }}
                                            @if ($jenis->kode)
                                                <span class="text-neutral-400">({{ $jenis->kode }})</span>
                                            @endif
                                        </label>
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                wire:model="bobotForm.{{ $jenis->id }}"
                                                class="w-24 rounded-lg px-3 py-2 text-right text-sm tabular-nums outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                            />
                                            <span class="text-sm text-neutral-400">%</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-4 flex items-center justify-between border-t border-neutral-100 pt-4">
                                <span class="text-sm font-medium text-neutral-600">Total</span>
                                <span class="text-sm font-semibold tabular-nums {{ $this->totalBobotForm() > 100 ? 'text-rose-600' : 'text-neutral-900' }}">
                                    {{ number_format($this->totalBobotForm(), 2) }}%
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="flex justify-end gap-2 border-t border-neutral-200 px-6 py-4">
                        <button type="button" wire:click="closeBobotForm" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                            Batal
                        </button>
                        <button
                            type="submit"
                            @disabled($this->jenisPenilaianOptions->isEmpty() || $this->totalBobotForm() > 100)
                            class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Auto Fill Bobot Penilaian (dalam modal detail) --}}
    @if ($showAutoFillConfirm)
        @php $autoFillTotal = (float) $this->jenisPenilaianOptions->sum('bobot'); @endphp
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-900/50 px-4 py-8">
            <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Auto Fill Bobot Penilaian</h3>
                    <button type="button" wire:click="closeAutoFillConfirm" class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    @error('autoFill')
                        <p class="mb-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 ring-1 ring-rose-100">{{ $message }}</p>
                    @enderror
                    <p class="mb-4 text-sm text-neutral-600">Isi bobot penilaian untuk mata kuliah ini sesuai nilai default dari master jenis penilaian. Ini akan menggantikan bobot yang sudah tersimpan.</p>

                    @if ($this->jenisPenilaianOptions->isEmpty())
                        <p class="py-6 text-center text-sm text-neutral-500">Tidak ada jenis penilaian.</p>
                    @else
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-neutral-200 bg-neutral-50 text-neutral-600">
                                    <th class="px-3 py-2 font-semibold">Jenis Penilaian</th>
                                    <th class="px-3 py-2 font-semibold">Kode</th>
                                    <th class="px-3 py-2 text-right font-semibold">Bobot (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->jenisPenilaianOptions as $jenis)
                                    <tr class="border-b border-neutral-100">
                                        <td class="px-3 py-2 font-medium text-neutral-800">{{ $jenis->nama }}</td>
                                        <td class="px-3 py-2 text-neutral-600">{{ $jenis->kode ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $jenis->bobot, 2), '0'), '.') }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-4 flex items-center justify-between border-t border-neutral-100 pt-4">
                            <span class="text-sm font-medium text-neutral-600">Total</span>
                            <span class="text-sm font-semibold tabular-nums {{ $autoFillTotal > 100 ? 'text-rose-600' : 'text-neutral-900' }}">
                                {{ number_format($autoFillTotal, 2) }}%
                            </span>
                        </div>
                        @if ($autoFillTotal > 100)
                            <p class="mt-2 text-sm text-rose-600">Total bobot default melebihi 100%. Gunakan "Kelola Bobot Penilaian" untuk mengatur manual.</p>
                        @endif
                    @endif
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-200 px-6 py-4">
                    <button type="button" wire:click="closeAutoFillConfirm" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="confirmAutoFill"
                        @disabled($this->jenisPenilaianOptions->isEmpty() || $autoFillTotal > 100)
                        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <i data-lucide="sparkles" class="h-4 w-4" aria-hidden="true"></i>
                        Konfirmasi & Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
