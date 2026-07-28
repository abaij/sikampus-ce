@section('title', ($strukturBiayaId ? 'Ubah' : 'Tambah') . ' Struktur Biaya — ' . config('app.name'))
@section('header_title', ($strukturBiayaId ? 'Ubah' : 'Tambah') . ' Struktur Biaya')
@section('header_icon', 'layout-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Struktur Biaya', 'route' => route('admin.keuangan.struktur-biaya')],
        ['label' => $strukturBiayaId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kategori Biaya</label>
                    <x-searchable-select
                        model="id_kategori_biaya"
                        :options="$this->kategoriBiayaOptions"
                        placeholder="— Pilih kategori biaya (opsional) —"
                    />
                    @error('id_kategori_biaya') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Program Studi</label>
                    <x-searchable-select
                        model="id_prodi"
                        :options="$this->prodiOptions"
                        placeholder="— Pilih program studi (opsional) —"
                    />
                    @error('id_prodi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Angkatan (semester masuk mahasiswa) *</label>
                    <x-searchable-select
                        model="id_angkatan"
                        :options="$this->semesterOptions"
                        placeholder="— Pilih angkatan —"
                    />
                    @error('id_angkatan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Periode Berlaku *</label>
                    <x-searchable-select
                        model="id_periode"
                        :options="$this->semesterOptions"
                        placeholder="— Pilih periode berlaku —"
                    />
                    @error('id_periode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Komponen Biaya</label>
                    <x-searchable-select
                        model="id_komponen_biaya"
                        :options="$this->komponenBiayaOptions"
                        placeholder="— Pilih komponen (opsional) —"
                    />
                    @error('id_komponen_biaya') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tahap</label>
                    <input
                        type="number"
                        min="1"
                        wire:model="tahap"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tahap') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Default 1 jika tidak diisi.</p>
                    @error('tahap') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nominal *</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        wire:model="nominal"
                        placeholder="Contoh: 5000000"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nominal') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('nominal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.keuangan.struktur-biaya') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
