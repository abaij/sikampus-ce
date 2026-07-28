@section('title', ($komponenBiayaId ? 'Ubah' : 'Tambah') . ' Komponen Biaya — ' . config('app.name'))
@section('header_title', ($komponenBiayaId ? 'Ubah' : 'Tambah') . ' Komponen Biaya')
@section('header_icon', 'layers-3')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Komponen Biaya', 'route' => route('admin.keuangan.komponen-biaya')],
        ['label' => $komponenBiayaId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode *</label>
                    <input
                        type="text"
                        wire:model="kode"
                        placeholder="Contoh: SPP, BPP"
                        maxlength="50"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('kode') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                    <input
                        type="text"
                        wire:model="nama"
                        placeholder="Nama komponen"
                        maxlength="255"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        wire:model="is_per_semester"
                        id="is_per_semester"
                        class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                    />
                    <label for="is_per_semester" class="text-sm font-medium text-neutral-700">Biaya per semester</label>
                    @error('is_per_semester') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        wire:model="is_akademik"
                        id="is_akademik"
                        class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                    />
                    <label for="is_akademik" class="text-sm font-medium text-neutral-700">Biaya akademik</label>
                    @error('is_akademik') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.keuangan.komponen-biaya') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
