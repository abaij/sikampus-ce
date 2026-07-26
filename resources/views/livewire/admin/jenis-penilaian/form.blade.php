@section('title', ($jenisPenilaianId ? 'Ubah' : 'Tambah') . ' Jenis Penilaian — ' . config('app.name'))
@section('header_title', ($jenisPenilaianId ? 'Ubah' : 'Tambah') . ' Jenis Penilaian')
@section('header_icon', 'clipboard-check')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Jenis Penilaian', 'route' => route('admin.akademik.jenis-penilaian')],
        ['label' => $jenisPenilaianId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode *</label>
                    <input type="text" wire:model="kode" placeholder="UTS, UAS, TUGAS, PRAKTIKUM" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('kode') ring-2 ring-red-500 @enderror shadow-border" />
                    <p class="mt-1.5 text-xs text-neutral-500">Kode unik untuk jenis penilaian (contoh: UTS, UAS)</p>
                    @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                    <input type="text" wire:model="nama" placeholder="Ujian Tengah Semester" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Bobot (%) *</label>
                    <input type="number" min="0" max="100" wire:model="bobot" placeholder="0" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('bobot') ring-2 ring-red-500 @enderror shadow-border" />
                    <p class="mt-1.5 text-xs text-neutral-500">
                        Bobot penilaian dalam persentase (0-100). Jenis penilaian lain saat ini berjumlah {{ $totalBobotLain }}%,
                        sisa kuota {{ max(0, 100 - $totalBobotLain) }}%.
                    </p>
                    @error('bobot') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                    <x-searchable-select
                        model="status"
                        :clearable="false"
                        :options="['manual' => 'Aktif (Diisi oleh dosen)', 'otomatis' => 'Tidak Aktif (Otomatis oleh sistem)']"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Menentukan apakah penilaian diisi manual oleh dosen atau otomatis oleh sistem.</p>
                    @error('status') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.akademik.jenis-penilaian') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
