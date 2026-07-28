@section('title', 'Generate Tagihan — ' . config('app.name'))
@section('header_title', 'Generate Tagihan')
@section('header_subtitle', 'Buat tagihan massal dari struktur biaya per periode, angkatan, prodi, dan komponen')
@section('header_icon', 'cog')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Tagihan', 'route' => route('admin.keuangan.tagihan')],
        ['label' => 'Generate Tagihan'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari periode, angkatan, prodi, atau komponen..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Periode</th>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Komponen</th>
                        <th class="px-4 py-3">Tahap Tersedia</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->groupedStrukturBiaya as $group)
                        <tr wire:key="grup-{{ $group['key'] }}">
                            <td class="px-4 py-3 text-neutral-700">
                                {{ $group['periode']->nama ?? '—' }}
                                @if ($group['periode']?->kode)
                                    <span class="text-xs text-neutral-500">({{ $group['periode']->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">
                                {{ $group['angkatan']->nama ?? '—' }}
                                @if ($group['angkatan']?->kode)
                                    <span class="text-xs text-neutral-500">({{ $group['angkatan']->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $group['prodi']->nama ?? 'Semua Prodi' }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $group['komponen_biaya']->nama ?? 'Semua Komponen' }}</td>
                            <td class="px-4 py-3 text-neutral-700">
                                {{ count($group['available_tahap']) > 0 ? implode(', ', $group['available_tahap']) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="openGenerateModal('{{ $group['key'] }}')"
                                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-sky-700 shadow-border transition hover:bg-sky-50"
                                >
                                    <i data-lucide="cog" class="h-4 w-4" aria-hidden="true"></i>
                                    Generate
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada data struktur biaya.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal: Konfirmasi Generate --}}
    @if ($confirmingGroupKey)
        @php $group = $this->groupedStrukturBiaya->firstWhere('key', $confirmingGroupKey); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Konfirmasi Generate Tagihan</h3>
                    <button type="button" wire:click="closeGenerateModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    <div class="rounded-lg bg-neutral-50 p-3 text-sm text-neutral-700 shadow-border">
                        <div>{{ $group['periode']->nama ?? '—' }} &middot; {{ $group['angkatan']->nama ?? '—' }}</div>
                        <div class="text-xs text-neutral-500">
                            {{ $group['prodi']->nama ?? 'Semua Prodi' }} &middot; {{ $group['komponen_biaya']->nama ?? 'Semua Komponen' }}
                        </div>
                    </div>

                    @error('confirmingGroupKey') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <div>
                        <p class="mb-2 text-sm font-medium text-neutral-700">Pilih tahap yang akan digenerate:</p>
                        <label class="mb-2 flex items-center gap-2 text-sm text-neutral-800">
                            <input type="radio" wire:model.live="opsiTahap" value="all" class="text-neutral-900 focus:ring-neutral-900/10" />
                            Semua tahapan
                        </label>
                        <label class="flex items-center gap-2 text-sm text-neutral-800">
                            <input type="radio" wire:model.live="opsiTahap" value="specific" class="text-neutral-900 focus:ring-neutral-900/10" />
                            Tahap tertentu
                        </label>
                    </div>

                    <div>
                        <select
                            wire:model.live="selectedTahap"
                            @disabled($opsiTahap !== 'specific')
                            class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('selectedTahap') ring-2 ring-red-500 @enderror shadow-border disabled:bg-neutral-100 disabled:text-neutral-400"
                        >
                            <option value="">— Pilih tahap —</option>
                            @foreach ($confirmingGroupAvailableTahap as $tahap)
                                <option value="{{ $tahap }}">Tahap {{ $tahap }}</option>
                            @endforeach
                        </select>
                        @error('selectedTahap') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                        <div class="text-sm font-semibold text-neutral-700">Jadwal Tagihan</div>
                        <div class="mt-1 text-sm text-neutral-600">{{ $this->jadwalPreview() }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-neutral-200 px-6 py-4">
                    <button type="button" wire:click="closeGenerateModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="generate" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">
                        <i data-lucide="cog" class="h-4 w-4" aria-hidden="true"></i>
                        Generate
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
