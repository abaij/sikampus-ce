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
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Semester *</label>
                    <input type="text" wire:model="nama" placeholder="Ganjil 2024/2025" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('nama') border-red-500 @enderror" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kode *</label>
                    <input type="text" wire:model="kode" placeholder="20241" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('kode') border-red-500 @enderror" />
                    <p class="mt-1.5 text-xs text-slate-500">Contoh: 20241 (tahun + semester ganjil/genap)</p>
                    @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                    <input type="datetime-local" wire:model="tanggal_mulai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('tanggal_mulai') border-red-500 @enderror" />
                    <p class="mt-1.5 text-xs text-slate-500">Opsional. Awal periode semester.</p>
                    @error('tanggal_mulai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal Selesai</label>
                    <input type="datetime-local" wire:model="tanggal_selesai" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('tanggal_selesai') border-red-500 @enderror" />
                    <p class="mt-1.5 text-xs text-slate-500">Opsional. Akhir periode semester.</p>
                    @error('tanggal_selesai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        Semester Aktif
                    </label>
                    <p class="mt-1.5 text-xs text-slate-500">Jika dicentang, semester ini akan menjadi aktif dan semester aktif lainnya akan dinonaktifkan.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.semester.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
