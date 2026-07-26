@section('title', ($semesterId ? 'Ubah' : 'Tambah') . ' Semester — ' . config('app.name'))
@section('header_title', ($semesterId ? 'Ubah' : 'Tambah') . ' Semester')
@section('header_icon', 'calendar-range')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Semester', 'route' => route('admin.semester.index')],
        ['label' => $semesterId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Semester *</label>
                    <input type="text" wire:model="nama" placeholder="Ganjil 2024/2025" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode *</label>
                    <input type="text" wire:model="kode" placeholder="20241" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('kode') ring-2 ring-red-500 @enderror shadow-border" />
                    <p class="mt-1.5 text-xs text-neutral-500">Contoh: 20241 (tahun + semester ganjil/genap)</p>
                    @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Mulai</label>
                    <input type="datetime-local" wire:model="tanggal_mulai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_mulai') ring-2 ring-red-500 @enderror shadow-border" />
                    <p class="mt-1.5 text-xs text-neutral-500">Opsional. Awal periode semester.</p>
                    @error('tanggal_mulai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Selesai</label>
                    <input type="datetime-local" wire:model="tanggal_selesai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_selesai') ring-2 ring-red-500 @enderror shadow-border" />
                    <p class="mt-1.5 text-xs text-neutral-500">Opsional. Akhir periode semester.</p>
                    @error('tanggal_selesai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-neutral-700">
                        <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900" />
                        Semester Aktif
                    </label>
                    <p class="mt-1.5 text-xs text-neutral-500">Jika dicentang, semester ini akan menjadi aktif dan semester aktif lainnya akan dinonaktifkan.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.semester.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
