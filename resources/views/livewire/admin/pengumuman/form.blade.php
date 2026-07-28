@section('title', ($pengumumanId ? 'Ubah' : 'Tambah') . ' Pengumuman — ' . config('app.name'))
@section('header_title', ($pengumumanId ? 'Ubah' : 'Tambah') . ' Pengumuman')
@section('header_icon', 'megaphone')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Pengumuman', 'route' => route('admin.administrasi.pengumuman')],
        ['label' => $pengumumanId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Judul *</label>
                    <input type="text" wire:model="judul" placeholder="Judul pengumuman" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('judul') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('judul') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Isi *</label>
                    <textarea wire:model="isi" rows="8" placeholder="Isi pengumuman" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('isi') ring-2 ring-red-500 @enderror shadow-border"></textarea>
                    @error('isi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Audien</label>
                    <x-searchable-select
                        model="audien"
                        :options="['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'staff' => 'Staff', 'alumni' => 'Alumni']"
                        placeholder="— Semua audien —"
                    />
                    @error('audien') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Prioritas</label>
                    <x-searchable-select
                        model="prioritas"
                        :options="['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi']"
                        placeholder="— Pilih prioritas —"
                    />
                    @error('prioritas') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Mulai</label>
                    <input type="datetime-local" wire:model="tanggal_mulai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_mulai') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_mulai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Selesai</label>
                    <input type="datetime-local" wire:model="tanggal_selesai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_selesai') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_selesai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
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
