@section('title', ($ktmId ? 'Ubah' : 'Tambah') . ' KTM — ' . config('app.name'))
@section('header_title', ($ktmId ? 'Ubah' : 'Tambah') . ' KTM')
@section('header_icon', 'id-card')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Mahasiswa', 'route' => route('admin.administrasi.mahasiswa')],
        ['label' => 'KTM', 'route' => route('admin.administrasi.ktm')],
        ['label' => $ktmId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mahasiswa *</label>

                    @if ($ktmId)
                        <input type="text" value="{{ $mahasiswaLabel }}" disabled class="w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500 shadow-border" />
                        <p class="mt-1.5 text-xs text-neutral-500">Mahasiswa tidak dapat diubah.</p>
                    @elseif ($id_mahasiswa)
                        <div class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm shadow-border">
                            <span class="font-medium text-neutral-900">{{ $mahasiswaLabel }}</span>
                            <button type="button" wire:click="clearMahasiswa" class="text-neutral-400 transition hover:text-neutral-600">
                                <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                            </button>
                        </div>
                    @else
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="mahasiswaSearch"
                                placeholder="Cari NIM atau nama mahasiswa..."
                                class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('id_mahasiswa') ring-2 ring-red-500 @enderror shadow-border"
                            />
                            @if ($mahasiswaSearch !== '')
                                <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                    @forelse ($this->mahasiswaResults as $m)
                                        <button
                                            type="button"
                                            wire:click="selectMahasiswa({{ $m->id }}, '{{ addslashes(trim(($m->nim ?? '').' - '.$m->nama)) }}')"
                                            class="block w-full px-3 py-2 text-left text-sm transition hover:bg-neutral-50"
                                        >
                                            <span class="font-medium text-neutral-900">{{ $m->nim ?? '—' }}</span>
                                            <span class="text-neutral-500"> — {{ $m->nama }}</span>
                                        </button>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil, atau mahasiswa ini sudah memiliki KTM.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    @endif
                    @error('id_mahasiswa') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nomor KTM</label>
                    <input type="text" wire:model="nomor_ktm" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nomor_ktm') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nomor_ktm') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                    <x-searchable-select
                        model="status"
                        :clearable="false"
                        :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']"
                    />
                </div>

                @if ($ktmId)
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ganti File KTM (opsional)</label>
                        <input
                            type="file"
                            wire:model="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="block w-full text-sm text-neutral-600 file:mr-4 file:rounded-lg file:border-0 file:bg-neutral-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-neutral-800"
                        />
                        <p class="mt-1.5 text-xs text-neutral-500">
                            Kosongkan untuk mempertahankan gambar yang ada. Untuk membuat ulang gambar dari template, gunakan tombol "Buat Ulang Gambar" di halaman daftar KTM.
                        </p>
                        @error('file') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="file" class="mt-1.5 text-xs text-neutral-500">Mengunggah…</div>
                    </div>
                @else
                    <div class="sm:col-span-2 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800">
                        Gambar KTM dibuat otomatis dari template KTM yang sudah diunggah, dengan data NIM, nama, dan prodi mahasiswa.
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.administrasi.ktm') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
