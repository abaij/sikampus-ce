@section('title', ($permissionId ? 'Ubah' : 'Tambah') . ' Permission — ' . config('app.name'))
@section('header_title', ($permissionId ? 'Ubah' : 'Tambah') . ' Permission')
@section('header_icon', 'key-round')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Pengguna'],
        ['label' => 'Permission', 'route' => route('admin.pengguna.permission.index')],
        ['label' => $permissionId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Permission *</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('name') ring-2 ring-red-500 @enderror shadow-border" placeholder="manage semester" />
                    <p class="mt-1.5 text-xs text-neutral-500">Format: kata kerja + objek (contoh: manage semester, view akademik)</p>
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Guard Name</label>
                    <x-searchable-select
                        model="guard_name"
                        :clearable="false"
                        :options="['web' => 'web', 'api' => 'api']"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Default: web</p>
                    @error('guard_name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.pengguna.permission.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
