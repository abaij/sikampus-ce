@section('title', ($tagihanId ? 'Ubah' : 'Tambah') . ' Tagihan — ' . config('app.name'))
@section('header_title', ($tagihanId ? 'Ubah' : 'Tambah') . ' Tagihan')
@section('header_icon', 'receipt')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Tagihan', 'route' => route('admin.keuangan.tagihan')],
        ['label' => $tagihanId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-700">Informasi Dasar</h3>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mahasiswa *</label>

                    @if ($id_mahasiswa)
                        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                            <span class="font-medium text-neutral-900">{{ $mahasiswaLabel }}</span>
                            <button type="button" wire:click="clearMahasiswa" class="text-neutral-400 transition hover:text-neutral-600">
                                <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                            </button>
                        </div>
                    @else
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="mahasiswaSearch"
                                placeholder="Cari NIM atau nama mahasiswa..."
                                class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('id_mahasiswa') ring-2 ring-red-500 @enderror shadow-border"
                            />
                            @if ($mahasiswaSearch !== '')
                                <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                    @forelse ($this->mahasiswaResults as $m)
                                        <button
                                            type="button"
                                            wire:click="selectMahasiswa({{ $m->id }}, '{{ addslashes(trim(($m->nim ?? '—').' — '.$m->nama)) }}')"
                                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition hover:bg-neutral-50"
                                        >
                                            <span>
                                                <span class="font-medium text-neutral-900">{{ $m->nim ?? '—' }}</span>
                                                <span class="text-neutral-500"> — {{ $m->nama }}</span>
                                            </span>
                                            @if ($m->prodi?->nama)
                                                <span class="shrink-0 text-xs text-neutral-500">{{ $m->prodi->nama }}</span>
                                            @endif
                                        </button>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    @endif
                    @error('id_mahasiswa') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Semester *</label>
                    <x-searchable-select model="id_semester" :options="$this->semesterOptions" placeholder="— Pilih semester —" />
                    @error('id_semester') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status *</label>
                    <select wire:model="status" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                        <option value="unpaid">Belum Bayar</option>
                        <option value="paid">Lunas</option>
                        <option value="expired">Kedaluwarsa</option>
                    </select>
                    @error('status') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Tagihan *</label>
                    <input type="date" wire:model="tanggal_tagihan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_tagihan') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_tagihan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Jatuh Tempo</label>
                    <input type="date" wire:model="tanggal_jatuh_tempo" min="{{ $tanggal_tagihan }}" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_jatuh_tempo') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_jatuh_tempo') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Pembayaran</label>
                    <input type="date" wire:model="tanggal_pembayaran" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_pembayaran') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_pembayaran') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan</label>
                    <textarea wire:model="keterangan" rows="3" placeholder="Keterangan tagihan (opsional)" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('keterangan') ring-2 ring-red-500 @enderror shadow-border"></textarea>
                    @error('keterangan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-neutral-700">Rincian Tagihan</h3>
                <button type="button" wire:click="addRincian" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah Rincian
                </button>
            </div>

            @error('rincian') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="space-y-4">
                @foreach ($rincian as $index => $row)
                    <div class="rounded-lg p-4 shadow-border" wire:key="rincian-{{ $index }}">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-semibold text-neutral-700">Rincian #{{ $index + 1 }}</span>
                            @if (count($rincian) > 1)
                                <button type="button" wire:click="removeRincian({{ $index }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                    <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    Hapus
                                </button>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Komponen Biaya *</label>
                                <select
                                    wire:model="rincian.{{ $index }}.id_komponen_biaya"
                                    class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error("rincian.$index.id_komponen_biaya") ring-2 ring-red-500 @enderror shadow-border"
                                >
                                    <option value="">— Pilih komponen biaya —</option>
                                    @foreach ($this->komponenBiayaOptions as $optId => $optLabel)
                                        <option value="{{ $optId }}">{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                                @error("rincian.$index.id_komponen_biaya") <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nominal *</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model="rincian.{{ $index }}.nominal"
                                    placeholder="0"
                                    class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error("rincian.$index.nominal") ring-2 ring-red-500 @enderror shadow-border"
                                />
                                @error("rincian.$index.nominal") <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between rounded-lg border-2 border-sky-200 bg-sky-50 p-4">
                <span class="text-sm font-semibold text-neutral-700">Total Tagihan:</span>
                <span class="text-lg font-bold text-sky-700">Rp{{ number_format($this->totalRincian(), 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $backUrl }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
