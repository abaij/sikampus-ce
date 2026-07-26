@section('title', ($jalurMasukId ? 'Ubah' : 'Tambah') . ' Jalur Masuk — ' . config('app.name'))
@section('header_title', ($jalurMasukId ? 'Ubah' : 'Tambah') . ' Jalur Masuk')
@section('header_icon', 'signpost')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Jalur Masuk', 'route' => route('admin.jalur-masuk.index')],
        ['label' => $jalurMasukId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Jalur Masuk *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                    <x-searchable-select
                        model="status"
                        :clearable="false"
                        :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Deskripsi</label>
                    <textarea wire:model="deskripsi" rows="3" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                </div>

                <div class="sm:col-span-2">
                    <span class="mb-2 block text-sm font-medium text-neutral-700">Ketentuan</span>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" wire:model="is_free_of_charge" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                            Gratis biaya pendaftaran
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" wire:model="has_selection" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                            Ada seleksi
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" wire:model="has_interview" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                            Ada wawancara
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" wire:model="has_physical_test" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                            Ada tes fisik
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" wire:model="has_psychological_test" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                            Ada tes psikologi
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" wire:model="has_medical_test" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                            Ada tes kesehatan
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.jalur-masuk.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
