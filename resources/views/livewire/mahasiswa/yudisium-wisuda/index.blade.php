@section('title', 'Yudisium & Wisuda — ' . config('app.name'))
@section('header_title', 'Yudisium & Wisuda')
@section('header_subtitle', 'Informasi kelulusan dan jadwal wisuda Anda ditampilkan pada halaman ini.')

@php
    $yudisium = $this->yudisium;
    $wisudaMhs = $this->wisudaMahasiswa;
    $formatDate = fn ($v) => $v ? \Carbon\Carbon::parse($v)->translatedFormat('d F Y') : '—';
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="rounded-xl bg-white p-5 shadow-border">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-neutral-900">
            <i data-lucide="file-check-2" class="h-5 w-5 text-sky-600" aria-hidden="true"></i>
            Data Yudisium
        </h2>
        @if (! $yudisium)
            <p class="text-sm text-neutral-500">Data yudisium belum tersedia.</p>
        @else
            <div class="grid gap-3 text-sm sm:grid-cols-2">
                <p><span class="font-medium text-neutral-700">Jenis keluar:</span> <span class="font-medium text-neutral-900">{{ $yudisium->jenis_keluar->nama ?? '—' }}</span></p>
                <p><span class="font-medium text-neutral-700">Tanggal keluar:</span> <span class="font-medium text-neutral-900">{{ $formatDate($yudisium->tgl_keluar) }}</span></p>
                <p><span class="font-medium text-neutral-700">No. ijazah:</span> <span class="font-medium text-neutral-900">{{ $yudisium->no_ijazah ?: '—' }}</span></p>
                <p><span class="font-medium text-neutral-700">No. SK yudisium:</span> <span class="font-medium text-neutral-900">{{ $yudisium->no_sk_yudisium ?: '—' }}</span></p>
                <p><span class="font-medium text-neutral-700">Tanggal SK yudisium:</span> <span class="font-medium text-neutral-900">{{ $formatDate($yudisium->tanggal_sk_yudisium) }}</span></p>
                <p><span class="font-medium text-neutral-700">IPK:</span> <span class="font-medium text-neutral-900">{{ $yudisium->ipk ?? '—' }}</span></p>
                <p class="sm:col-span-2"><span class="font-medium text-neutral-700">Judul skripsi:</span> <span class="font-medium text-neutral-900">{{ $yudisium->judul_skripsi ?: '—' }}</span></p>
                <p class="sm:col-span-2"><span class="font-medium text-neutral-700">Keterangan:</span> <span class="font-medium text-neutral-900">{{ $yudisium->keterangan ?: '—' }}</span></p>
            </div>
        @endif
    </section>

    <section class="rounded-xl bg-white p-5 shadow-border">
        <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-neutral-900">
            <i data-lucide="graduation-cap" class="h-5 w-5 text-sky-600" aria-hidden="true"></i>
            Data Wisuda
        </h2>
        @if (! $wisudaMhs)
            <div class="space-y-3">
                <p class="text-sm text-neutral-500">
                    Anda belum terdaftar sebagai peserta wisuda. Foto untuk ijazah/buku wisuda bisa diunggah setelah jadwal tersedia.
                </p>
                @if ($this->canDaftarWisuda)
                    <button type="button" wire:click="openDaftarModal" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                        Daftar wisuda
                    </button>
                @elseif ($yudisium)
                    <p class="text-xs text-amber-700">Jadwal wisuda aktif belum tersedia. Silakan tunggu informasi jadwal dari kampus.</p>
                @endif
            </div>
        @else
            <div class="space-y-4 text-sm">
                <div class="grid gap-3 sm:grid-cols-2">
                    <p><span class="font-medium text-neutral-700">Status peserta:</span> <span class="font-medium text-neutral-900">{{ $wisudaMhs->status ?: '—' }}</span></p>
                    <p><span class="font-medium text-neutral-700">No. SK wisuda:</span> <span class="font-medium text-neutral-900">{{ $wisudaMhs->no_sk_wisuda ?: '—' }}</span></p>
                    <p><span class="font-medium text-neutral-700">Tanggal SK wisuda:</span> <span class="font-medium text-neutral-900">{{ $formatDate($wisudaMhs->tanggal_sk_wisuda) }}</span></p>
                    <p><span class="font-medium text-neutral-700">Nama wisuda:</span> <span class="font-medium text-neutral-900">{{ $wisudaMhs->wisuda->nama ?? '—' }}</span></p>
                    <p><span class="font-medium text-neutral-700">Tanggal wisuda:</span> <span class="font-medium text-neutral-900">{{ $formatDate($wisudaMhs->wisuda->tanggal_wisuda ?? null) }}</span></p>
                </div>

                @if ($wisudaMhs->file_sk_wisuda)
                    <a href="{{ asset('storage/'.ltrim($wisudaMhs->file_sk_wisuda, '/')) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-sky-700 hover:underline">
                        Lihat file SK wisuda
                        <i data-lucide="external-link" class="h-3.5 w-3.5" aria-hidden="true"></i>
                    </a>
                @endif

                <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                    <p class="mb-2 font-medium text-neutral-800">Foto untuk ijazah & buku wisuda</p>
                    @if ($wisudaMhs->foto)
                        <img src="{{ asset('storage/'.ltrim($wisudaMhs->foto, '/')) }}" alt="Foto wisuda" class="mb-3 h-48 w-40 rounded-lg object-cover shadow-border" />
                    @else
                        <p class="mb-3 text-sm text-neutral-500">Belum ada foto yang diunggah.</p>
                    @endif

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            type="file"
                            wire:model="fotoFile"
                            accept=".jpg,.jpeg,.png"
                            @disabled(! $this->canUploadFoto)
                            class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-sky-700 disabled:opacity-50"
                        />
                        <button
                            type="button"
                            wire:click="uploadFoto"
                            wire:loading.attr="disabled"
                            wire:target="uploadFoto,fotoFile"
                            @disabled(! $this->canUploadFoto || ! $fotoFile)
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i data-lucide="upload-cloud" class="h-4 w-4" aria-hidden="true"></i>
                            Upload
                        </button>
                    </div>
                    @if (! $this->canUploadFoto)
                        <p class="mt-2 text-xs text-amber-700">Upload hanya tersedia jika Anda sudah memiliki jadwal wisuda.</p>
                    @endif
                    @error('fotoFile') <p class="mt-2 text-xs text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif
    </section>

    @if ($showDaftarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="pr-8 text-lg font-semibold text-neutral-900">Form pendaftaran wisuda</h3>
                    <button type="button" wire:click="closeDaftarModal" class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600" aria-label="Tutup">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="mt-1 text-sm text-neutral-500">
                    Pilih jadwal wisuda aktif. Setelah submit, pendaftaran Anda akan diverifikasi oleh bagian akademik untuk diproses lebih lanjut.
                </p>

                @error('daftarIdWisuda')
                    <p class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 ring-1 ring-rose-100">{{ $message }}</p>
                @enderror

                <div class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Jadwal wisuda</label>
                    <select wire:model="daftarIdWisuda" class="w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10">
                        <option value="">Pilih jadwal wisuda</option>
                        @foreach ($this->jadwalWisudaAktif as $j)
                            <option value="{{ $j->id }}">{{ $j->nama }} — {{ $formatDate($j->tanggal_wisuda) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeDaftarModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">Batal</button>
                    <button
                        type="button"
                        wire:click="submitDaftar"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:opacity-50"
                    >
                        Submit pendaftaran
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
