@section('title', ($pembayaranId ? 'Ubah' : 'Tambah') . ' Pembayaran — ' . config('app.name'))
@section('header_title', ($pembayaranId ? 'Ubah' : 'Tambah') . ' Pembayaran')
@section('header_icon', 'wallet')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Pembayaran', 'route' => $backUrl],
        ['label' => $pembayaranId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        @if (! $pembayaranId)
            {{-- Mode tambah: cari mahasiswa lewat NIM, lalu pilih salah satu tagihan yang belum lunas. --}}
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-700">Informasi Mahasiswa</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIM *</label>
                        <input
                            type="text"
                            wire:model.live.debounce.400ms="nim"
                            placeholder="Masukkan NIM mahasiswa"
                            class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                        />
                        <p class="mt-1.5 text-xs text-neutral-500">Ketik NIM mahasiswa untuk mencari tagihan yang belum lunas.</p>
                    </div>
                    @if ($this->mahasiswaTagihan['mahasiswa'])
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mahasiswa</label>
                            <div class="rounded-lg px-3 py-2.5 text-sm shadow-border">
                                <div class="font-semibold text-neutral-900">{{ $this->mahasiswaTagihan['mahasiswa']->nama }}</div>
                                <div class="text-xs text-neutral-500">NIM: {{ $this->mahasiswaTagihan['mahasiswa']->nim }}</div>
                            </div>
                        </div>
                    @elseif (trim($nim) !== '')
                        <div class="flex items-end">
                            <p class="text-sm text-rose-600">Mahasiswa dengan NIM tersebut tidak ditemukan.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-700">Pilih Tagihan</h3>
                @error('id_tagihan') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($this->mahasiswaTagihan['mahasiswa'] && $this->mahasiswaTagihan['tagihan']->isEmpty())
                    <p class="text-sm text-neutral-500">Tidak ada tagihan yang belum lunas untuk mahasiswa ini.</p>
                @endif

                <div class="space-y-2">
                    @foreach ($this->mahasiswaTagihan['tagihan'] as $tagihan)
                        <button
                            type="button"
                            wire:click="selectTagihan({{ $tagihan->id }})"
                            class="flex w-full items-center justify-between gap-3 rounded-lg px-4 py-3 text-left text-sm transition shadow-border {{ $id_tagihan === $tagihan->id ? 'bg-neutral-900 text-white' : 'hover:bg-neutral-50' }}"
                        >
                            <span>
                                <span class="font-semibold">{{ $tagihan->no_tagihan }}</span>
                                <span class="{{ $id_tagihan === $tagihan->id ? 'text-neutral-300' : 'text-neutral-500' }}"> — {{ $tagihan->semester->nama ?? '—' }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block font-semibold">Rp{{ number_format((float) $tagihan->total, 0, ',', '.') }}</span>
                                <span class="block text-xs {{ $id_tagihan === $tagihan->id ? 'text-neutral-300' : 'text-neutral-500' }}">Sisa Rp{{ number_format((float) $tagihan->sisa_tagihan, 0, ',', '.') }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Mode ubah: tagihan tidak dapat diganti, hanya ditampilkan. --}}
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-700">Tagihan</h3>
                <div class="rounded-lg px-4 py-3 text-sm shadow-border">
                    <div class="font-semibold text-neutral-900">{{ $lockedTagihan->no_tagihan }}</div>
                    <div class="text-xs text-neutral-500">
                        {{ $lockedTagihan->mahasiswa->nama ?? '—' }} ({{ $lockedTagihan->mahasiswa->nim ?? '—' }})
                        &middot; {{ $lockedTagihan->semester->nama ?? '—' }}
                    </div>
                    <div class="mt-1 text-xs text-neutral-500">
                        Total tagihan Rp{{ number_format((float) $lockedTagihan->total, 0, ',', '.') }}
                        &middot; Sisa saat ini Rp{{ number_format($sisaTagihanSaatIni, 0, ',', '.') }} (di luar pembayaran ini)
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-700">Informasi Pembayaran</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nominal *</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        wire:model="nominal"
                        placeholder="0"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nominal') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('nominal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Pembayaran *</label>
                    <input
                        type="date"
                        wire:model="tanggal_pembayaran"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_pembayaran') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('tanggal_pembayaran') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Metode Pembayaran</label>
                    <select wire:model="metode_pembayaran" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                        <option value="">Pilih Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="kartu">Kartu Debit/Kredit</option>
                        <option value="e-wallet">E-Wallet</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan</label>
                    <textarea wire:model="keterangan" rows="3" placeholder="Keterangan pembayaran (opsional)" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
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
