@section('title', 'Detail Ujian Sidang — ' . config('app.name'))
@section('header_title', 'Detail ujian sidang')
@section('header_subtitle', $this->pengujiSidang->ujianSidang?->tugasAkhir?->mahasiswa ? $this->pengujiSidang->ujianSidang->tugasAkhir->mahasiswa->nama . ' · ' . ($this->pengujiSidang->ujianSidang->tugasAkhir->mahasiswa->nim ?? '—') : null)
@section('breadcrumb')
    <a href="{{ route('dosen.ujian-sidang') }}" class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5" aria-hidden="true"></i>
        Kembali ke daftar ujian sidang
    </a>
@endsection

<div class="space-y-6">
    @php
        $p = $this->pengujiSidang;
        $u = $p->ujianSidang;
        $ta = $u?->tugasAkhir;
        $labelSidang = fn (?string $s) => match ($s) {
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'returned' => 'Dikembalikan',
            default => $s ?? '—',
        };
        $labelPenguji = fn (?string $s) => match ($s) {
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $s ?? '—',
        };
        $pengujiLain = $u?->penguji?->filter(fn ($row) => $row->id !== $p->id) ?? collect();
    @endphp

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 ring-1 ring-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex gap-1 rounded-xl bg-neutral-50 p-1 shadow-border">
        <button type="button" wire:click="setTab('detail')" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ $tab === 'detail' ? 'bg-white text-neutral-900 shadow-border' : 'text-neutral-600 hover:text-neutral-900' }}">
            Info ujian sidang
        </button>
        <button type="button" wire:click="setTab('nilai')" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ $tab === 'nilai' ? 'bg-white text-neutral-900 shadow-border' : 'text-neutral-600 hover:text-neutral-900' }}">
            Nilai ujian sidang
        </button>
    </div>

    @if ($tab === 'detail')
        <div class="space-y-6">
            <div class="rounded-2xl bg-white shadow-border">
                <div class="border-b border-neutral-200 px-6 py-3">
                    <h2 class="text-sm font-semibold text-neutral-800">Mahasiswa &amp; tugas akhir</h2>
                </div>
                <dl class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mahasiswa</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $ta?->mahasiswa?->nama ?? '—' }}</dd>
                        <dd class="text-xs text-neutral-500">NIM: {{ $ta?->mahasiswa?->nim ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program studi</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $ta?->mahasiswa?->prodi?->nama ?? '—' }}</dd>
                        @if ($ta?->mahasiswa?->prodi?->kode)
                            <dd class="text-xs text-neutral-500">Kode: {{ $ta->mahasiswa->prodi->kode }}</dd>
                        @endif
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Judul tugas akhir</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $ta?->judul ?? '—' }}</dd>
                    </div>
                    @if ($ta?->deskripsi)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Deskripsi</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-neutral-700">{{ $ta->deskripsi }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-2xl bg-white shadow-border">
                <div class="border-b border-neutral-200 px-6 py-3">
                    <h2 class="text-sm font-semibold text-neutral-800">Jadwal &amp; status sidang</h2>
                </div>
                <dl class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester sidang</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $u?->semester?->nama ?? '—' }}</dd>
                        @if ($u?->semester?->kode)
                            <dd class="text-xs text-neutral-500">{{ $u->semester->kode }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Status pengajuan sidang</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $labelSidang($u?->status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Tanggal daftar</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $u?->tanggal_daftar?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Peran Anda</dt>
                        <dd class="mt-1">
                            @if ($p->is_ketua)
                                <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">Ketua penguji</span>
                            @else
                                <span class="text-sm text-neutral-800">Anggota penguji</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Waktu mulai</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $u?->tanggal_ujian_mulai?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Waktu selesai</dt>
                        <dd class="mt-1 text-sm text-neutral-900">{{ $u?->tanggal_ujian_selesai?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Berkas laporan / proposal</dt>
                        <dd class="mt-1">
                            @if ($u?->file_proposal)
                                <a href="{{ asset('storage/' . ltrim($u->file_proposal, '/')) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-sky-700 shadow-border hover:bg-neutral-50">
                                    <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
                                    Unduh berkas
                                </a>
                            @else
                                <span class="text-sm text-neutral-500">Belum ada berkas.</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @if ($pengujiLain->isNotEmpty())
                <div class="rounded-2xl bg-white shadow-border">
                    <div class="border-b border-neutral-200 px-6 py-3">
                        <h2 class="text-sm font-semibold text-neutral-800">Penguji lain</h2>
                    </div>
                    <ul class="divide-y divide-neutral-200 p-2">
                        @foreach ($pengujiLain as $row)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="file-text" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-900">{{ $row->dosen?->nama ?? '—' }}</p>
                                        <p class="text-xs text-neutral-500">{{ $row->dosen?->kode_dosen ?? '—' }}</p>
                                    </div>
                                </div>
                                @if ($row->is_ketua)
                                    <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">Ketua</span>
                                @else
                                    <span class="text-xs text-neutral-600">Anggota</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @else
        <div class="space-y-6">
            <div class="rounded-2xl bg-white shadow-border">
                <div class="border-b border-neutral-200 px-6 py-3">
                    <h2 class="text-sm font-semibold text-neutral-800">Penilaian Anda sebagai penguji</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">Isi nilai dan catatan penilaian.</p>
                </div>
                <form wire:submit="saveNilai" class="space-y-4 p-6">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-neutral-600">Nilai (0–999,99)</label>
                        <input type="text" inputmode="decimal" wire:model="formNilai" placeholder="Contoh: 85 atau 85,5" class="w-full max-w-xs rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('formNilai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-neutral-600">Catatan</label>
                        <textarea wire:model="formCatatan" rows="5" placeholder="Catatan penilaian (opsional)" class="w-full max-w-2xl rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                        @error('formCatatan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                            <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                            Simpan penilaian
                        </button>
                        <button type="button" wire:click="resetForm" class="text-sm font-medium text-neutral-600 hover:text-neutral-900">
                            Reset form
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl bg-white shadow-border">
                <div class="border-b border-neutral-200 px-6 py-3">
                    <h2 class="text-sm font-semibold text-neutral-800">Nilai penguji lain</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">Ringkasan penilaian dari dosen penguji lain pada ujian sidang ini (hanya baca).</p>
                </div>
                @if ($pengujiLain->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-neutral-600">Tidak ada penguji lain pada jadwal ini.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                            <thead>
                                <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                    <th class="px-6 py-3">Dosen</th>
                                    <th class="px-6 py-3">Peran</th>
                                    <th class="px-6 py-3">Status penilaian</th>
                                    <th class="min-w-[180px] px-6 py-3">Catatan</th>
                                    <th class="px-6 py-3">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @foreach ($pengujiLain as $row)
                                    <tr wire:key="lain-{{ $row->id }}" class="hover:bg-neutral-50/70">
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-neutral-900">{{ $row->dosen?->nama ?? '—' }}</p>
                                            <p class="text-xs text-neutral-500">{{ $row->dosen?->kode_dosen ?? '—' }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            @if ($row->is_ketua)
                                                <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">Ketua</span>
                                            @else
                                                <span class="text-neutral-600">Anggota</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-neutral-600">{{ $labelPenguji($row->status) }}</td>
                                        <td class="max-w-md px-6 py-4 align-top text-neutral-700">
                                            <span class="whitespace-pre-wrap break-words">{{ $row->catatan ?: '—' }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 font-medium text-neutral-900">{{ $row->nilai ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($p->is_ketua)
                <div class="rounded-2xl bg-white shadow-border">
                    <div class="border-b border-amber-100 bg-amber-50/80 px-6 py-3">
                        <div class="flex flex-wrap items-start gap-2">
                            <i data-lucide="award" class="h-5 w-5 shrink-0 text-amber-600" aria-hidden="true"></i>
                            <div>
                                <h2 class="text-sm font-semibold text-neutral-900">Pratinjau finalisasi nilai (ketua penguji)</h2>
                                <p class="mt-0.5 text-xs text-neutral-600">
                                    Rata-rata nilai semua penguji dipetakan ke huruf mutu menurut rentang nilai jenjang prodi, lalu dapat ditulis ke transkrip untuk KRS Tugas Akhir.
                                </p>
                            </div>
                        </div>
                    </div>

                    @php $preview = $this->previewFinalisasi; @endphp

                    @if (! ($preview['ok'] ?? false))
                        <div class="mx-6 my-4 rounded-xl bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-border">
                            <p class="font-semibold">Pratinjau tidak tersedia</p>
                            <p class="mt-1">{{ $preview['message'] ?? 'Data tidak lengkap.' }}</p>
                        </div>
                    @else
                        <div class="space-y-3 px-6 py-4 text-sm">
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Rata-rata nilai penguji</dt>
                                    <dd class="mt-0.5 text-lg font-semibold text-neutral-900">{{ $preview['rata_rata'] }}</dd>
                                    <dd class="text-xs text-neutral-500">dari {{ $preview['jumlah_penguji'] }} penguji</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jenjang</dt>
                                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $preview['jenjang']['nama'] }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Rentang terpilih</dt>
                                    <dd class="mt-0.5 text-neutral-800">
                                        {{ $preview['rentang']['nilai_huruf'] }} (AM {{ $preview['rentang']['nilai_angka'] }}) — rentang skor {{ $preview['rentang']['nilai_rendah'] }}–{{ $preview['rentang']['nilai_tinggi'] }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">KRS &amp; SKS</dt>
                                    <dd class="mt-0.5 text-neutral-800">
                                        id KRS {{ $preview['krs_id'] }}
                                        @if ($preview['sks'] > 0)
                                            · {{ $preview['sks'] }} SKS
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                            @if ($preview['nilai_eksisting'])
                                <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-700">
                                    Nilai di transkrip saat ini: <strong>{{ $preview['nilai_eksisting']['huruf_mutu'] ?? '—' }}</strong>
                                    @if ($preview['nilai_eksisting']['angka_mutu'] !== null)
                                        (AM {{ $preview['nilai_eksisting']['angka_mutu'] }})
                                    @endif
                                    @if ($preview['nilai_eksisting']['is_final'])
                                        · final
                                    @endif
                                </p>
                            @else
                                <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-600">
                                    Belum ada baris nilai untuk KRS ini — akan dibuat saat finalisasi.
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 px-6 py-4">
                        <button type="button" wire:click="reloadPreviewFinalisasi" class="rounded-lg px-4 py-2 text-sm font-semibold text-neutral-700 shadow-border hover:bg-neutral-50">
                            Muat ulang pratinjau
                        </button>
                        <button
                            type="button"
                            wire:click="openFinalisasiConfirm"
                            @if (! ($preview['ok'] ?? false)) disabled @endif
                            class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i data-lucide="award" class="h-4 w-4" aria-hidden="true"></i>
                            Finalisasi nilai ke transkrip
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($showFinalisasiConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-neutral-900">Konfirmasi finalisasi nilai</h2>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Nilai akan ditulis ke tabel nilai (transkrip) untuk KRS mata kuliah Tugas Akhir mahasiswa ini. Pastikan pratinjau sudah sesuai.
                </p>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeFinalisasiConfirm" class="rounded-lg px-4 py-2 text-sm font-semibold text-neutral-700 shadow-border hover:bg-neutral-50">
                        Batal
                    </button>
                    <button type="button" wire:click="finalisasiNilai" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">
                        Ya, finalisasi
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
