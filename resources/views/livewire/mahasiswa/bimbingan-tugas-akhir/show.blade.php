@php
    $ta = $this->tugasAkhir;
    $pembimbingList = $ta->pembimbing;
    $rows = $this->bimbinganRows;
    $statusLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft', 'submitted' => 'Terkirim', 'approved' => 'Disetujui', 'rejected' => 'Ditolak',
        default => $s ?? '—',
    };
@endphp

@section('title', 'Riwayat Bimbingan — ' . config('app.name'))
@section('header_title', 'Riwayat bimbingan')
@section('header_subtitle', 'Pertemuan bimbingan dan catatan pembimbing untuk tugas akhir yang dipilih.')

@section('breadcrumb')
    <a href="{{ route('mahasiswa.akhir-studi.bimbingan-tugas-akhir') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali ke daftar tugas akhir
    </a>
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-xl bg-white p-4 shadow-border">
        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Judul tugas akhir</p>
        <p class="mt-1 text-lg font-semibold text-neutral-900">{{ $ta->judul ?? '—' }}</p>
        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-neutral-600">
            @if ($ta->semester)
                <span>Semester: <span class="font-medium text-neutral-800">{{ $ta->semester->nama }}</span> <span class="text-xs text-neutral-500">({{ $ta->semester->kode }})</span></span>
            @endif
            <span>Status pengajuan: <span class="font-medium text-neutral-800">{{ $statusLabel($ta->status) }}</span></span>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-border">
        <div class="border-b border-neutral-100 bg-neutral-50/80 px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-800">Riwayat bimbingan</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">Tambah entri untuk mencatat pertemuan atau catatan ke pembimbing. Tanggal + pembimbing tidak boleh sama dengan entri yang sudah ada.</p>
                </div>
                <button
                    type="button"
                    wire:click="openAddModal"
                    @disabled($pembimbingList->isEmpty())
                    title="{{ $pembimbingList->isEmpty() ? 'Belum ada pembimbing yang ditugaskan pada tugas akhir ini.' : '' }}"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-500"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah bimbingan
                </button>
            </div>
        </div>

        @if ($pembimbingList->isEmpty())
            <div class="px-4 py-6 text-sm text-amber-900">
                Belum ada dosen pembimbing yang terdaftar. Hubungi program studi bila ini tidak sesuai.
            </div>
        @elseif ($rows->isEmpty())
            <div class="border-t border-dashed border-neutral-200 bg-neutral-50/50 p-10 text-center">
                <i data-lucide="clipboard-list" class="mx-auto mb-3 h-10 w-10 text-neutral-400" aria-hidden="true"></i>
                <p class="font-medium text-neutral-700">Belum ada entri bimbingan</p>
                <p class="mt-2 text-sm text-neutral-500">Gunakan tombol "Tambah bimbingan" untuk mencatat pertemuan atau catatan kepada pembimbing.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3">Tanggal</th>
                            <th class="whitespace-nowrap px-4 py-3">Dosen</th>
                            <th class="min-w-[140px] px-4 py-3">Dicatat oleh</th>
                            <th class="min-w-[200px] px-4 py-3">Catatan dosen</th>
                            <th class="min-w-[180px] px-4 py-3">Catatan mahasiswa</th>
                            <th class="whitespace-nowrap px-4 py-3">Lampiran</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($rows as $row)
                            <tr wire:key="bimbingan-{{ $row->id }}" class="align-top text-neutral-800">
                                <td class="whitespace-nowrap px-4 py-3 text-neutral-700">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i data-lucide="calendar" class="h-3.5 w-3.5 text-neutral-400" aria-hidden="true"></i>
                                        {{ $row->tanggal_bimbingan?->translatedFormat('d M Y') ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-start gap-1.5">
                                        <i data-lucide="user" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-neutral-400" aria-hidden="true"></i>
                                        <span>
                                            {{ $row->dosen->nama ?? '—' }}
                                            @if ($row->dosen?->kode_dosen)
                                                <span class="block text-xs text-neutral-500">Kode: {{ $row->dosen->kode_dosen }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td class="max-w-[160px] px-4 py-3 text-xs text-neutral-600">{{ $row->created_by ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-700">
                                    @if ($row->catatan_dosen)
                                        <span class="whitespace-pre-wrap">{{ $row->catatan_dosen }}</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-neutral-700">
                                    @if ($row->catatan_mahasiswa)
                                        <span class="whitespace-pre-wrap">{{ $row->catatan_mahasiswa }}</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($row->file)
                                        <a href="{{ asset('storage/'.ltrim($row->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold text-sky-700 hover:text-sky-900">
                                            <i data-lucide="file-text" class="h-4 w-4" aria-hidden="true"></i>
                                            Unduh
                                        </a>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        wire:click="openDetailModal({{ $row->id }})"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                        title="Lihat detail & isi catatan"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Modal tambah bimbingan --}}
    @if ($showAddModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-neutral-900">Tambah bimbingan</h2>
                    <button type="button" wire:click="closeAddModal" class="rounded-lg p-1 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800" aria-label="Tutup">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Tanggal <span class="text-rose-600">*</span></label>
                        <input type="date" wire:model="addTanggal" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10" />
                        @error('addTanggal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Pembimbing <span class="text-rose-600">*</span></label>
                        <select wire:model="addIdDosen" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10">
                            <option value="">— Pilih pembimbing —</option>
                            @foreach ($pembimbingList as $p)
                                <option value="{{ $p->id_dosen }}">{{ $p->dosen->nama ?? "Dosen #{$p->id_dosen}" }}{{ $p->dosen?->kode_dosen ? " ({$p->dosen->kode_dosen})" : '' }}</option>
                            @endforeach
                        </select>
                        @error('addIdDosen') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Catatan Anda</label>
                        <textarea wire:model="addCatatan" rows="4" placeholder="Ringkasan pertemuan, pertanyaan, atau rencana kerja…" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"></textarea>
                        @error('addCatatan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Lampiran (opsional)</label>
                        <input type="file" wire:model="addFile" class="mt-1 block w-full text-sm text-neutral-700" />
                        <p class="mt-1 text-xs text-neutral-500">Maks. 10 MB.</p>
                        @error('addFile') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeAddModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">Batal</button>
                    <button
                        type="button"
                        wire:click="submitAdd"
                        wire:loading.attr="disabled"
                        wire:target="submitAdd,addFile"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal detail / catatan mahasiswa --}}
    @if ($this->detailRow)
        @php $row = $this->detailRow; @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-neutral-900">Detail bimbingan</h2>
                    <button type="button" wire:click="closeDetailModal" class="rounded-lg p-1 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800" aria-label="Tutup">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="space-y-4 border-b border-neutral-100 pb-4 text-sm">
                    @if ($row->tugasAkhir?->judul)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Judul tugas akhir</p>
                            <p class="mt-1 font-medium text-neutral-900">{{ $row->tugasAkhir->judul }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Tanggal bimbingan</p>
                        <p class="mt-1 inline-flex items-center gap-1.5 text-neutral-800">
                            <i data-lucide="calendar" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                            {{ $row->tanggal_bimbingan?->translatedFormat('d M Y') ?? '—' }}
                        </p>
                    </div>
                    @if ($row->created_by)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Dicatat oleh</p>
                            <p class="mt-1 text-neutral-800">{{ $row->created_by }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Dosen pembimbing</p>
                        <p class="mt-1 inline-flex items-start gap-1.5 text-neutral-800">
                            <i data-lucide="user" class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" aria-hidden="true"></i>
                            <span>
                                {{ $row->dosen->nama ?? '—' }}
                                @if ($row->dosen?->kode_dosen)
                                    <span class="block text-xs text-neutral-500">Kode: {{ $row->dosen->kode_dosen }}</span>
                                @endif
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Catatan dosen</p>
                        <div class="mt-1 rounded-lg bg-neutral-50/80 px-3 py-2 text-neutral-800 shadow-border">
                            @if ($row->catatan_dosen)
                                <p class="whitespace-pre-wrap">{{ $row->catatan_dosen }}</p>
                            @else
                                <p class="text-neutral-400">Belum ada catatan dari dosen.</p>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lampiran dosen</p>
                        <div class="mt-1">
                            @if ($row->file)
                                <a href="{{ asset('storage/'.ltrim($row->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 font-semibold text-sky-700 hover:text-sky-900">
                                    <i data-lucide="file-text" class="h-4 w-4" aria-hidden="true"></i>
                                    Buka lampiran
                                </a>
                            @else
                                <span class="text-neutral-400">Tidak ada lampiran.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Catatan Anda (refleksi / pertanyaan / rencana tindak lanjut)</label>
                        <textarea wire:model="detailCatatanDraft" rows="5" placeholder="Tuliskan catatan untuk pembimbing…" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"></textarea>
                        <p class="mt-1 text-xs text-neutral-500">Kosongkan lalu simpan untuk menghapus catatan.</p>
                        @error('detailCatatanDraft') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Lampiran (opsional)</label>
                        <input type="file" wire:model="detailFile" class="mt-1 block w-full text-sm text-neutral-700" />
                        <p class="mt-1 text-xs text-neutral-500">Maks. 10 MB. Mengunggah berkas baru akan mengganti lampiran yang sudah ada.</p>
                        @error('detailFile') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeDetailModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">Tutup</button>
                    <button
                        type="button"
                        wire:click="saveDetail"
                        wire:loading.attr="disabled"
                        wire:target="saveDetail,detailFile"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Simpan catatan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
