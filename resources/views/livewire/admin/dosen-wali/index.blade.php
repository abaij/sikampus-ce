@section('title', 'Dosen Wali — ' . config('app.name'))
@section('header_title', 'Dosen Wali')
@section('header_subtitle', 'Kelola dosen wali dan kuota bimbingan akademik')
@section('header_icon', 'users')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Dosen', 'route' => route('admin.administrasi.dosen')],
        ['label' => 'Dosen Wali'],
    ]])
@endsection

{{--
    Hanya link navigasi biasa yang boleh masuk ke page_actions — section itu dirender oleh
    layouts.web DI LUAR root komponen Livewire (lihat @yield('page_actions') vs @yield('content')
    di layouts/web.blade.php), jadi wire:click/wire:model di sini tidak akan pernah terikat.
    Tombol Import dan Tetapkan Kuota karena itu dipindah ke dalam <div> root di bawah.
--}}
@section('page_actions')
    <a
        href="{{ route('admin.administrasi.dosen-wali.template') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
        Download Template
    </a>
@endsection

<div>
    @if (\App\Support\PanelAccess::can(auth()->user(), 'dosen wali', 'update'))
        <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
                <i data-lucide="upload" class="h-4 w-4" aria-hidden="true"></i>
                <span wire:loading.remove wire:target="importFile">Import</span>
                <span wire:loading wire:target="importFile">Mengimport...</span>
                <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="hidden" />
            </label>

            <button
                type="button"
                wire:click="openKuotaModal"
                class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
            >
                <i data-lucide="settings" class="h-4 w-4" aria-hidden="true"></i>
                Tetapkan Kuota
            </button>
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('import_error'))
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ session('import_error') }}</span>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="mb-4 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Sebagian baris dilewati saat import:</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5">
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @error('importFile')
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-200 p-4">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari kode atau nama dosen..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kode Dosen</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi (Kaprodi)</th>
                        <th class="px-4 py-3 text-center">Mahasiswa Bimbingan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($dosenList as $dosen)
                        <tr wire:key="dosen-{{ $dosen->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $dosen->kode_dosen ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $dosen->gelar_depan ? $dosen->gelar_depan.' ' : '' }}{{ $dosen->nama }}{{ $dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : '' }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $dosen->prodiAsKaprodi->nama ?? '—' }}
                                @if ($dosen->prodiAsKaprodi?->kode)
                                    <span class="text-xs text-neutral-400">({{ $dosen->prodiAsKaprodi->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-neutral-600">
                                {{ $dosen->mahasiswa_bimbingan_count }} / {{ $dosen->kuota_bimbingan_akademik ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.administrasi.dosen-wali.show', $dosen->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                    class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                >
                                    <i data-lucide="users" class="h-4 w-4" aria-hidden="true"></i>
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Belum ada data dosen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $dosenList->links() }}
        </div>
    </div>

    @if ($showKuotaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Tetapkan Kuota Bimbingan Akademik</h3>
                <p class="mt-2 text-sm text-neutral-600">
                    Nilai kuota akan diterapkan untuk semua dosen yang belum memiliki kuota bimbingan akademik (nilai 0 atau belum diisi). Dosen yang sudah memiliki kuota tidak akan diubah.
                </p>

                <div class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kuota mahasiswa bimbingan</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="kuotaInput"
                        placeholder="Contoh: 20"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('kuotaInput') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('kuotaInput') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeKuotaModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="applyKuota" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">
                        Terapkan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
