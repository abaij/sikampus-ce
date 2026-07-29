@section('title', 'Detail Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Detail tugas akhir')
@section('header_subtitle', $this->tugasAkhir->mahasiswa?->nama ? 'Bimbingan untuk ' . $this->tugasAkhir->mahasiswa->nama : null)
@section('breadcrumb')
    <a href="{{ route('dosen.tugas-akhir') }}" class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5" aria-hidden="true"></i>
        Kembali ke daftar tugas akhir
    </a>
@endsection

<div class="space-y-6">
    @php $ta = $this->tugasAkhir; @endphp

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="border-b border-neutral-200 px-6 py-3">
            <h2 class="text-sm font-semibold text-neutral-800">Data tugas akhir</h2>
        </div>
        <div class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2 lg:col-span-3">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Judul</p>
                <p class="mt-1 text-base font-semibold text-neutral-900">{{ $ta->judul ?? '—' }}</p>
                @if ($ta->judul_en)
                    <p class="mt-1 text-sm text-neutral-600">{{ $ta->judul_en }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mahasiswa</p>
                <p class="mt-1 font-medium text-neutral-900">{{ $ta->mahasiswa?->nama ?? '—' }}</p>
                <p class="text-xs text-neutral-500">NIM: {{ $ta->mahasiswa?->nim ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program studi</p>
                <p class="mt-1 text-neutral-800">
                    {{ $ta->mahasiswa?->prodi?->nama ?? '—' }}
                    @if ($ta->mahasiswa?->prodi?->kode)
                        <span class="text-neutral-500">({{ $ta->mahasiswa->prodi->kode }})</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester TA</p>
                <p class="mt-1 text-neutral-800">
                    {{ $ta->semester?->nama ?? '—' }}
                    @if ($ta->semester?->kode)
                        <span class="text-xs text-neutral-500">({{ $ta->semester->kode }})</span>
                    @endif
                </p>
            </div>
            @if ($ta->topik)
                <div class="sm:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Topik</p>
                    <p class="mt-1 text-neutral-800">{{ $ta->topik }}</p>
                </div>
            @endif
            @if ($ta->deskripsi)
                <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Deskripsi</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-neutral-700">{{ $ta->deskripsi }}</p>
                </div>
            @endif
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Status pengajuan</p>
                <p class="mt-1">
                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                        Disetujui
                    </span>
                </p>
            </div>
            @if ($ta->file)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Berkas</p>
                    <a href="{{ asset('storage/' . ltrim($ta->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-sky-600 hover:underline">
                        <i data-lucide="file-text" class="h-4 w-4" aria-hidden="true"></i>
                        Unduh / lihat berkas
                    </a>
                </div>
            @endif
        </div>
    </div>

    @if ($ta->pembimbing->isNotEmpty())
        <div class="rounded-2xl bg-white shadow-border">
            <div class="border-b border-neutral-200 px-6 py-3">
                <h2 class="text-sm font-semibold text-neutral-800">Pembimbing</h2>
            </div>
            <ul class="divide-y divide-neutral-200 px-6 py-2">
                @foreach ($ta->pembimbing as $p)
                    <li class="py-3 text-sm">
                        <span class="font-medium text-neutral-900">{{ $p->dosen?->nama ?? '—' }}</span>
                        @if ($p->dosen?->kode_dosen)
                            <span class="ml-2 text-neutral-500">({{ $p->dosen->kode_dosen }})</span>
                        @endif
                        @if ($p->tanggal_penugasan)
                            <span class="mt-1 block text-xs text-neutral-500">Penugasan: {{ $p->tanggal_penugasan->format('Y-m-d') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-neutral-200 px-6 py-3">
            <div>
                <h2 class="text-sm font-semibold text-neutral-800">Riwayat bimbingan</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Catatan pertemuan bimbingan Anda dengan mahasiswa pada tugas akhir ini.</p>
            </div>
            <button type="button" wire:click="openBimbinganModal" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Tambah bimbingan
            </button>
        </div>

        @php $riwayat = $this->riwayatBimbingan; @endphp
        @if (empty($riwayat))
            <div class="p-10 text-center">
                <i data-lucide="book-open" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">Belum ada entri riwayat bimbingan.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">Tanggal</th>
                            <th class="px-6 py-3">Catatan mahasiswa</th>
                            <th class="px-6 py-3">Catatan dosen</th>
                            <th class="px-6 py-3">Berkas</th>
                            <th class="px-6 py-3">Dibuat oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($riwayat as $r)
                            <tr wire:key="bimbingan-{{ $r->id }}" class="hover:bg-neutral-50/70">
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-800">{{ $r->tanggal_bimbingan?->format('Y-m-d') ?? '—' }}</td>
                                <td class="max-w-xs px-6 py-4 align-top text-neutral-700">
                                    <span class="whitespace-pre-wrap">{{ $r->catatan_mahasiswa ?? '—' }}</span>
                                </td>
                                <td class="max-w-xs px-6 py-4 align-top text-neutral-700">
                                    <span class="whitespace-pre-wrap">{{ $r->catatan_dosen ?? '—' }}</span>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    @if ($r->file)
                                        <a href="{{ asset('storage/' . ltrim($r->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-sky-600 hover:underline">
                                            <i data-lucide="file-text" class="h-4 w-4" aria-hidden="true"></i>
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 align-top text-neutral-700">{{ $r->created_by ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($showBimbinganModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h3 class="text-lg font-semibold text-neutral-900">Tambah bimbingan</h3>
                    <button type="button" wire:click="closeBimbinganModal" class="rounded-lg p-1 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>
                <form wire:submit="saveBimbingan" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal bimbingan <span class="text-red-600">*</span></label>
                        <input type="date" wire:model="form_tanggal_bimbingan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('form_tanggal_bimbingan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Catatan dosen</label>
                        <textarea wire:model="form_catatan_dosen" rows="5" placeholder="Ringkasan materi / arahan untuk mahasiswa…" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                        @error('form_catatan_dosen') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Berkas (opsional)</label>
                        <input type="file" wire:model="form_file" class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100" />
                        @error('form_file') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="form_file" class="mt-1.5 text-xs text-neutral-500">Mengunggah…</div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeBimbinganModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                            Batal
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
