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
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Jalur Masuk *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('nama') border-red-500 @enderror" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                    <x-searchable-select
                        model="status"
                        :clearable="false"
                        :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Deskripsi</label>
                    <textarea wire:model="deskripsi" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></textarea>
                </div>

                <div class="sm:col-span-2">
                    <span class="mb-2 block text-sm font-medium text-slate-700">Ketentuan</span>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="is_free_of_charge" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                            Gratis biaya pendaftaran
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="has_selection" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                            Ada seleksi
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="has_interview" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                            Ada wawancara
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="has_physical_test" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                            Ada tes fisik
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="has_psychological_test" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                            Ada tes psikologi
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="has_medical_test" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                            Ada tes kesehatan
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.jalur-masuk.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
