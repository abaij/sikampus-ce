@section('title', ($fakultasId ? 'Ubah' : 'Tambah') . ' Fakultas — ' . config('app.name'))
@section('header_title', ($fakultasId ? 'Ubah' : 'Tambah') . ' Fakultas')
@section('header_icon', 'building-2')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Fakultas', 'route' => route('admin.fakultas.index')],
        ['label' => $fakultasId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Fakultas *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode</label>
                    <input type="text" wire:model="kode" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('kode') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
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

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Dekan</label>
                    <x-searchable-select
                        model="id_dekan"
                        :options="$dosenOptions"
                        placeholder="— Pilih dosen —"
                    />
                    @error('id_dekan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Website</label>
                    <input type="text" wire:model="website" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telepon</label>
                    <input type="text" wire:model="telepon" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat</label>
                    <textarea wire:model="alamat" rows="2" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kota</label>
                    <input type="text" wire:model="kota" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Provinsi</label>
                    <input type="text" wire:model="provinsi" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                    <input type="text" wire:model="kode_pos" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Negara</label>
                    <input type="text" wire:model="negara" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.fakultas.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
