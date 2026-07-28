@section('title', ($aturanAksesKeuanganId ? 'Ubah' : 'Tambah') . ' Aturan Akses Keuangan — ' . config('app.name'))
@section('header_title', ($aturanAksesKeuanganId ? 'Ubah' : 'Tambah') . ' Aturan Akses Keuangan')
@section('header_icon', 'shield-check')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Aturan Akses Keuangan', 'route' => route('admin.keuangan.aturan-akses-keuangan')],
        ['label' => $aturanAksesKeuanganId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Akses *</label>
                    <input
                        type="text"
                        wire:model="kode_akses"
                        placeholder="Contoh: krs, uas, uts"
                        maxlength="100"
                        class="w-full rounded-lg px-3 py-2.5 font-mono text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('kode_akses') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Hanya huruf kecil, angka, dan underscore. Dipakai untuk mencocokkan pengecekan akses (mis. pengajuan KRS, akses ujian).</p>
                    @error('kode_akses') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama</label>
                    <input
                        type="text"
                        wire:model="nama"
                        placeholder="Nama deskriptif (opsional)"
                        maxlength="255"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Persentase Minimum (%)</label>
                    <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        wire:model="persentase_minimum"
                        placeholder="Kosongkan jika tidak ada syarat"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('persentase_minimum') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Minimum persentase tagihan yang harus lunas. Kosongkan berarti tidak ada syarat pelunasan.</p>
                    @error('persentase_minimum') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                    <select
                        wire:model="status"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('status') ring-2 ring-red-500 @enderror shadow-border"
                    >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                    @error('status') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.keuangan.aturan-akses-keuangan') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
