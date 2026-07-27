@php
    $statusLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft',
        'submitted' => 'Terkirim',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'returned' => 'Dikembalikan',
        default => '—',
    };
    $ta = $this->tugasAkhir;
    $sidang = $this->ujianSidang;
    $preview = $this->previewFinalisasi;
@endphp

@section('title', 'Detail Ujian Sidang — ' . config('app.name'))
@section('header_title', 'Detail Ujian Sidang')
@section('header_subtitle', $ta->mahasiswa?->nama)
@section('header_icon', 'gavel')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Tugas Akhir', 'route' => route('admin.akademik.tugas-akhir')],
        ['label' => $ta->mahasiswa?->nama ?? 'Detail', 'route' => route('admin.akademik.tugas-akhir.show', $ta->id)],
        ['label' => 'Ujian Sidang'],
    ]])
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Mahasiswa</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-xs text-neutral-500">Nama</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">NIM</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->nim ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Program studi</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->prodi?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Email</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->email ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Tugas akhir</h2>
        <div class="space-y-3">
            <div>
                <p class="mb-1 text-xs text-neutral-500">Judul</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $ta->judul }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Semester pengajuan TA</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $ta->semester?->nama ?? '—' }}{{ $ta->semester?->kode ? " ({$ta->semester->kode})" : '' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Status pengajuan judul</p>
                <span class="inline-flex rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-800">{{ $statusLabel($ta->status) }}</span>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Dosen pembimbing</p>
                @if ($ta->pembimbing->isEmpty())
                    <p class="mt-1 text-sm text-neutral-500">Belum ada dosen pembimbing.</p>
                @else
                    <ul class="mt-1 space-y-1">
                        @foreach ($ta->pembimbing as $p)
                            <li class="text-sm">
                                <span class="font-medium text-neutral-900">{{ $p->dosen?->nama ?? '—' }}</span>
                                @if ($p->dosen?->kode_dosen)
                                    <span class="text-neutral-600">({{ $p->dosen->kode_dosen }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Pengajuan ujian sidang</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-xs text-neutral-500">Semester ujian sidang</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $sidang->semester?->nama ?? '—' }}{{ $sidang->semester?->kode ? " ({$sidang->semester->kode})" : '' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Tanggal daftar</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $sidang->tanggal_daftar?->format('d M Y H:i') ?? '—' }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="mb-1 text-xs text-neutral-500">Status pengajuan ujian sidang</p>
                <div class="mt-1 flex flex-wrap items-end gap-3">
                    <select wire:model="statusPengajuan" class="min-w-[200px] flex-1 rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                        <option value="draft">Draft</option>
                        <option value="submitted">Terkirim</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                    </select>
                    <button type="button" wire:click="saveStatusPengajuan" class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800">
                        Simpan status
                    </button>
                </div>
                @error('statusPengajuan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-neutral-500">Status tersimpan di server: <span class="font-medium text-neutral-700">{{ $statusLabel($sidang->status) }}</span></p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="flex items-center gap-2">
            <i data-lucide="calendar" class="h-5 w-5 text-sky-600" aria-hidden="true"></i>
            <h2 class="text-base font-semibold text-neutral-900">Jadwal ujian</h2>
        </div>
        <p class="mt-1 text-sm text-neutral-600">Atur tanggal dan jam mulai serta selesai ujian sidang. Kosongkan untuk menghapus jadwal.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Waktu mulai</label>
                <input type="datetime-local" wire:model="tanggalMulai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                @error('tanggalMulai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Waktu selesai</label>
                <input type="datetime-local" wire:model="tanggalSelesai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                @error('tanggalSelesai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" wire:click="saveJadwal" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800">
                Simpan jadwal
            </button>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="text-base font-semibold text-neutral-900">Dosen penguji</h2>
        <p class="mt-1 text-sm text-neutral-600">Tambahkan atau hapus dosen penguji untuk jadwal ini. Satu dosen tidak boleh diduplikasi.</p>

        <div class="mt-4 rounded-xl border border-dashed border-neutral-200 bg-neutral-50/80 p-4">
            <p class="mb-3 text-sm font-semibold text-neutral-800">Tambah penguji</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px] flex-1">
                    <label class="mb-1 block text-xs text-neutral-500">Dosen</label>
                    <x-searchable-select
                        model="pengujiDosenId"
                        :options="$this->dosenOptions"
                        optionLabel="label"
                        placeholder="— Cari nama atau kode dosen —"
                    />
                    @error('pengujiDosenId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex cursor-pointer items-center gap-2 pb-2 text-sm text-neutral-700">
                    <input type="checkbox" wire:model="pengujiIsKetua" class="rounded border-neutral-300" />
                    Ketua penguji
                </label>
                <button type="button" wire:click="addPenguji" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah
                </button>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr class="border-b border-neutral-200">
                        <th class="py-3 pr-3">Dosen</th>
                        <th class="py-3 pr-3">Peran</th>
                        <th class="py-3 pr-3">Nilai</th>
                        <th class="py-3 pr-3">Status</th>
                        <th class="py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($sidang->penguji as $p)
                        <tr wire:key="penguji-{{ $p->id }}">
                            <td class="py-3 pr-3">
                                <p class="font-medium text-neutral-900">{{ $p->dosen?->nama ?? '—' }}</p>
                                <p class="text-xs text-neutral-500">{{ $p->dosen?->kode_dosen ?? '—' }} · NIDN {{ $p->dosen?->nidn ?? '—' }}</p>
                            </td>
                            <td class="py-3 pr-3">
                                @if ($p->is_ketua)
                                    <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">Ketua</span>
                                @else
                                    <span class="text-neutral-600">Anggota</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3 text-neutral-700">{{ $p->nilai ?? '—' }}</td>
                            <td class="py-3 pr-3 text-neutral-700">{{ $statusLabel($p->status) }}</td>
                            <td class="py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" wire:click="openEditPenguji({{ $p->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900" title="Ubah">
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" wire:click="confirmDeletePenguji({{ $p->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700" title="Hapus">
                                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-neutral-500">Belum ada dosen penguji. Gunakan form di atas untuk menambahkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="flex items-start gap-3">
            <i data-lucide="award" class="h-7 w-7 shrink-0 text-amber-600" aria-hidden="true"></i>
            <div>
                <h2 class="text-base font-semibold text-neutral-900">Finalisasi nilai</h2>
                <p class="mt-1 text-sm text-neutral-600">
                    Rata-rata nilai dari semua dosen penguji dipetakan ke huruf mutu menurut rentang nilai jenjang program studi mahasiswa,
                    lalu disimpan ke tabel nilai untuk KRS mata kuliah Tugas Akhir (jenis TA).
                </p>
            </div>
        </div>

        @if (! ($preview['ok'] ?? false))
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950">
                <p class="font-semibold">Pratinjau tidak tersedia</p>
                <p class="mt-1">{{ $preview['message'] ?? '' }}</p>
            </div>
        @else
            <div class="mt-4 space-y-3 text-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Rata-rata nilai penguji</p>
                        <p class="mt-0.5 text-lg font-semibold text-neutral-900">{{ $preview['rata_rata'] }}</p>
                        <p class="text-xs text-neutral-500">dari {{ $preview['jumlah_penguji'] }} penguji</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jenjang</p>
                        <p class="mt-0.5 font-medium text-neutral-900">{{ $preview['jenjang']['nama'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Rentang yang dipilih</p>
                        <p class="mt-0.5 text-neutral-800">{{ $preview['rentang']['nilai_huruf'] }} (AM {{ $preview['rentang']['nilai_angka'] }}) — rentang skor {{ $preview['rentang']['nilai_rendah'] }}–{{ $preview['rentang']['nilai_tinggi'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">KRS &amp; SKS</p>
                        <p class="mt-0.5 text-neutral-800">id KRS {{ $preview['krs_id'] }}{{ $preview['sks'] > 0 ? " · {$preview['sks']} SKS" : '' }}</p>
                    </div>
                </div>
                @if ($preview['nilai_eksisting'])
                    <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-700">
                        Nilai di transkrip saat ini: <strong>{{ $preview['nilai_eksisting']['huruf_mutu'] ?? '—' }}</strong>
                        {{ $preview['nilai_eksisting']['angka_mutu'] !== null ? "(AM {$preview['nilai_eksisting']['angka_mutu']})" : '' }}
                        {{ $preview['nilai_eksisting']['is_final'] ? '· ditandai final' : '' }}
                    </p>
                @else
                    <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-600">Belum ada baris nilai untuk KRS ini — akan dibuat saat finalisasi.</p>
                @endif
            </div>
        @endif

        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <button type="button" wire:click="reloadPreviewFinalisasi" class="rounded-lg px-4 py-2 text-sm font-semibold text-neutral-700 shadow-border transition hover:bg-neutral-50">
                Muat ulang pratinjau
            </button>
            <button
                type="button"
                wire:click="openFinalisasiConfirm"
                @disabled(! ($preview['ok'] ?? false))
                class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <i data-lucide="award" class="h-4 w-4" aria-hidden="true"></i>
                Finalisasi nilai
            </button>
        </div>
    </div>

    {{-- Modal: ubah penguji --}}
    @if ($editingPengujiId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Ubah Penguji</h3>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Dosen *</label>
                        <x-searchable-select
                            model="editPengujiDosenId"
                            :options="$this->dosenOptions"
                            optionLabel="label"
                            placeholder="— Cari nama atau kode dosen —"
                        />
                        @error('editPengujiDosenId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                        <input type="checkbox" wire:model="editPengujiIsKetua" class="rounded border-neutral-300" />
                        Ketua penguji
                    </label>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nilai</label>
                        <input type="number" step="0.01" min="0" max="999.99" wire:model="editPengujiNilai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('editPengujiNilai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                        <select wire:model="editPengujiStatus" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                            <option value="draft">Draft</option>
                            <option value="submitted">Terkirim</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                        @error('editPengujiStatus') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Catatan</label>
                        <textarea wire:model="editPengujiCatatan" rows="2" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                        @error('editPengujiCatatan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" wire:click="cancelEditPenguji" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="saveEditPenguji" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: konfirmasi hapus penguji --}}
    @if ($confirmingPengujiDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus penguji?</h3>
                <p class="mt-2 text-sm text-neutral-600">Dosen penguji ini akan dihapus dari ujian sidang.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeletePenguji" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="deletePenguji" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">Hapus</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: konfirmasi finalisasi nilai --}}
    @if ($showFinalisasiConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Konfirmasi finalisasi nilai</h3>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Nilai akan ditulis ke tabel nilai (transkrip) untuk KRS mata kuliah Tugas Akhir mahasiswa ini. Pastikan rata-rata dan huruf mutu
                    di pratinjau sudah sesuai sebelum melanjutkan.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeFinalisasiConfirm" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="finalisasiNilai" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700">Ya, finalisasi</button>
                </div>
            </div>
        </div>
    @endif
</div>
