@section('title', ($jenisKeringananBiayaId ? 'Ubah' : 'Tambah') . ' Jenis Keringanan Biaya — ' . config('app.name'))
@section('header_title', ($jenisKeringananBiayaId ? 'Ubah' : 'Tambah') . ' Jenis Keringanan Biaya')
@section('header_icon', 'percent')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Jenis Keringanan Biaya', 'route' => route('admin.keuangan.jenis-keringanan-biaya')],
        ['label' => $jenisKeringananBiayaId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                    <input
                        type="text"
                        wire:model="nama"
                        placeholder="Contoh: Keringanan Yatim Piatu, Beasiswa Prestasi"
                        maxlength="255"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                        <input type="checkbox" wire:model="is_persentase" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                        Nilai berupa persentase (bukan nominal tetap)
                    </label>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">
                        {{ $is_persentase ? 'Persentase (%) *' : 'Nominal (Rp) *' }}
                    </label>
                    <input
                        type="number"
                        min="0"
                        @if ($is_persentase) max="100" @endif
                        step="0.01"
                        wire:model="nominal"
                        placeholder="0"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nominal') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">
                        @if ($is_persentase)
                            Persentase potongan dari total tagihan (maksimal 100).
                        @else
                            Nominal potongan tetap dalam rupiah.
                        @endif
                    </p>
                    @error('nominal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                        <input type="checkbox" wire:model="is_active" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" />
                        Aktif
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan</label>
                    <textarea
                        wire:model="keterangan"
                        rows="3"
                        placeholder="Keterangan tambahan (opsional)"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('keterangan') ring-2 ring-red-500 @enderror shadow-border"
                    ></textarea>
                    @error('keterangan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.keuangan.jenis-keringanan-biaya') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
