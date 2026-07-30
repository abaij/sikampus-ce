@php
    $jadwal = $this->jadwal;
    $matkul = $jadwal->kelas->kurikulumMatkul->matkul ?? null;
@endphp

@section('title', ($matkul->nama ?? 'Detail Jadwal') . ' — ' . config('app.name'))
@section('header_title', $matkul->nama ?? 'Detail Jadwal')
@section('header_subtitle', 'Kelas: ' . ($jadwal->kelas->nama ?? '-'))

@section('breadcrumb')
    <a href="{{ route('mahasiswa.jadwal') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali ke jadwal
    </a>
@endsection

@php
    $labelKehadiran = function (?string $status) {
        return match ($status) {
            'hadir' => 'Hadir',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alfa' => 'Alfa (tanpa keterangan)',
            default => 'Belum diisi',
        };
    };
    $badgeKehadiranClass = function (?string $status) {
        return match ($status) {
            'hadir' => 'bg-emerald-100 text-emerald-800',
            'izin' => 'bg-amber-100 text-amber-800',
            'sakit' => 'bg-sky-100 text-sky-800',
            'alfa' => 'bg-rose-100 text-rose-800',
            default => 'bg-neutral-100 text-neutral-600',
        };
    };
    $tabs = [
        ['key' => 'detail', 'label' => 'Detail jadwal', 'icon' => 'layout-list'],
        ['key' => 'tugas', 'label' => 'Tugas', 'icon' => 'clipboard-list'],
        ['key' => 'kehadiran', 'label' => 'Kehadiran', 'icon' => 'user-check'],
    ];
@endphp

<div class="space-y-6">
    <div class="rounded-2xl bg-white p-5 shadow-border">
        <span class="rounded bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">{{ $matkul->kode ?? '-' }}</span>
        @if ($jadwal->kelas->semester)
            <p class="mt-1 text-xs text-neutral-500">{{ $jadwal->kelas->semester->nama }} ({{ $jadwal->kelas->semester->kode }})</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-2 border-t border-neutral-100 pt-4">
            @foreach ($tabs as $t)
                <button
                    type="button"
                    wire:click="setTab('{{ $t['key'] }}')"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ $tab === $t['key'] ? 'bg-sky-600 text-white shadow-sm' : 'bg-white text-neutral-700 shadow-border hover:bg-neutral-50' }}"
                >
                    <i data-lucide="{{ $t['icon'] }}" class="h-4 w-4" aria-hidden="true"></i>
                    {{ $t['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($tab === 'detail')
        <div class="overflow-hidden rounded-2xl bg-white shadow-border">
            <div class="border-b border-neutral-100 bg-neutral-50 px-5 py-3">
                <h2 class="text-base font-semibold text-neutral-900">Informasi slot jadwal</h2>
            </div>
            <div class="grid gap-4 p-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-500">Hari</dt>
                    <dd class="font-medium text-neutral-900">{{ ucfirst((string) $jadwal->hari) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Jam</dt>
                    <dd class="font-medium text-neutral-900">{{ substr((string) $jadwal->jam_mulai, 0, 5) }} — {{ substr((string) $jadwal->jam_selesai, 0, 5) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Ruangan</dt>
                    <dd class="font-medium text-neutral-900">{{ $jadwal->ruangan->nama ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Jenis kuliah</dt>
                    <dd class="font-medium text-neutral-900">{{ $jadwal->jenisKuliah->nama ?? '-' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-neutral-500">Dosen</dt>
                    <dd class="font-medium text-neutral-900">{{ $jadwal->dosen->pluck('dosen.nama')->filter()->implode(', ') ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Status KRS</dt>
                    <dd>
                        <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $this->krsStatus === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $this->krsStatus === 'approved' ? 'Disetujui' : 'Menunggu' }}
                        </span>
                    </dd>
                </div>
            </div>
        </div>

        <section>
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-neutral-900">
                <i data-lucide="book-open" class="h-5 w-5 text-sky-600" aria-hidden="true"></i>
                Perkuliahan & materi
            </h2>

            @if ($this->perkuliahanRows->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-200 bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-600">
                    Belum ada data perkuliahan yang terhubung dengan slot jadwal ini.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($this->perkuliahanRows as $p)
                        <div wire:key="perkuliahan-{{ $p->id }}" class="overflow-hidden rounded-xl bg-white shadow-border">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 bg-neutral-50 px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-semibold text-neutral-900">Pertemuan ke-{{ $p->pertemuan_ke }}</span>
                                    @if ($p->tanggal)
                                        <span class="text-neutral-600">{{ \Carbon\Carbon::parse($p->tanggal)->translatedFormat('d F Y') }}</span>
                                    @endif
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeKehadiranClass($p->kehadiran_saya?->status) }}">
                                    <i data-lucide="user-check" class="h-3 w-3" aria-hidden="true"></i>
                                    {{ $labelKehadiran($p->kehadiran_saya?->status) }}
                                </span>
                            </div>
                            <div class="space-y-4 p-4">
                                @php $teks = $p->realisasi_materi ?: $p->materi; @endphp
                                @if ($teks)
                                    <div>
                                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            {{ $p->realisasi_materi ? 'Realisasi materi' : 'Materi (perkuliahan)' }}
                                        </h3>
                                        <p class="whitespace-pre-wrap text-sm text-neutral-800">{{ $teks }}</p>
                                    </div>
                                @endif

                                @if ($p->kehadiran_saya?->keterangan)
                                    <div class="text-sm">
                                        <span class="font-medium text-neutral-700">Keterangan kehadiran: </span>
                                        <span class="text-neutral-600">{{ $p->kehadiran_saya->keterangan }}</span>
                                    </div>
                                @endif

                                <div>
                                    <h3 class="mb-2 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                        <i data-lucide="file-text" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                        Materi / lampiran
                                    </h3>
                                    @if ($p->materi_perkuliahan->isEmpty())
                                        <p class="text-sm text-neutral-400">Tidak ada lampiran</p>
                                    @else
                                        <ul class="space-y-2">
                                            @foreach ($p->materi_perkuliahan as $m)
                                                <li>
                                                    <a
                                                        href="{{ asset('storage/'.ltrim($m->file, '/')) }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700"
                                                    >
                                                        {{ $m->nama ?: $m->file }}
                                                        <i data-lucide="external-link" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if ($tab === 'tugas')
        <section class="overflow-hidden rounded-2xl bg-white shadow-border">
            <div class="border-b border-neutral-100 bg-neutral-50 px-5 py-3">
                <h2 class="text-base font-semibold text-neutral-900">Tugas untuk slot jadwal ini</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Informasi tugas dari dosen; status pengumpulan merujuk pada data Anda.</p>
            </div>

            @if (session('status_tugas'))
                <div class="m-5 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                    <span>{{ session('status_tugas') }}</span>
                </div>
            @endif

            <div class="p-5">
                @if ($this->tugasRows->isEmpty())
                    <p class="py-8 text-center text-sm text-neutral-600">Belum ada tugas untuk jadwal ini.</p>
                @else
                    <ul class="space-y-4">
                        @foreach ($this->tugasRows as $t)
                            <li wire:key="tugas-{{ $t->id }}" class="rounded-xl bg-neutral-50/60 p-4 shadow-border">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h3 class="font-semibold text-neutral-900">{{ $t->nama }}</h3>
                                    @if ($t->dosen?->nama)
                                        <span class="text-xs text-neutral-500">Oleh: {{ $t->dosen->nama }}</span>
                                    @endif
                                </div>
                                @if ($t->deskripsi)
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-neutral-700">{{ $t->deskripsi }}</p>
                                @endif
                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-600">
                                    @if ($t->tanggal_mulai)
                                        <span>Mulai: {{ $t->tanggal_mulai->translatedFormat('d M Y H:i') }}</span>
                                    @endif
                                    @if ($t->tanggal_selesai)
                                        <span>Selesai: {{ $t->tanggal_selesai->translatedFormat('d M Y H:i') }}</span>
                                    @endif
                                </div>
                                @if ($t->file)
                                    <a href="{{ asset('storage/'.ltrim($t->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:text-sky-700">
                                        Lampiran tugas
                                        <i data-lucide="external-link" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                    </a>
                                @endif

                                <div class="mt-3 border-t border-neutral-200 pt-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Pengumpulan Anda</p>
                                    @if ($t->pengumpulan_saya)
                                        <div class="mt-1 text-sm text-neutral-700">
                                            @if ($t->pengumpulan_saya->tanggal_submit)
                                                <p>Dikumpulkan: {{ $t->pengumpulan_saya->tanggal_submit->translatedFormat('d M Y H:i') }}</p>
                                            @endif
                                            @if ($t->pengumpulan_saya->keterangan)
                                                <p class="mt-1 text-neutral-600">{{ $t->pengumpulan_saya->keterangan }}</p>
                                            @endif
                                            @if ($t->pengumpulan_saya->file)
                                                <a href="{{ asset('storage/'.ltrim($t->pengumpulan_saya->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 font-medium text-sky-600 hover:text-sky-700">
                                                    File pengumpulan
                                                    <i data-lucide="external-link" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <p class="mt-1 text-sm text-neutral-500">Belum ada pengumpulan tercatat.</p>
                                    @endif

                                    @if ($submittingTugasId === $t->id)
                                        <form wire:submit="submitTugas" class="mt-3 space-y-3 rounded-lg border border-dashed border-neutral-200 bg-white px-3 py-3">
                                            <p class="text-xs font-medium text-neutral-700">{{ $t->pengumpulan_saya?->file ? 'Perbarui pengumpulan' : 'Unggah tugas' }}</p>
                                            <div>
                                                <label class="mb-1 block text-xs text-neutral-500">
                                                    Berkas {{ $t->pengumpulan_saya?->file ? '(opsional — kosongkan untuk mempertahankan berkas lama)' : '(wajib)' }}
                                                </label>
                                                <input type="file" wire:model="tugasFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="block w-full text-sm text-neutral-700" />
                                                @error('tugasFile') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs text-neutral-500">Keterangan (opsional)</label>
                                                <textarea wire:model="tugasKeterangan" rows="2" class="w-full rounded-lg px-3 py-2 text-sm shadow-border" placeholder="Catatan untuk dosen..."></textarea>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700" wire:loading.attr="disabled" wire:target="submitTugas,tugasFile">
                                                    <i data-lucide="upload" class="h-4 w-4" aria-hidden="true"></i>
                                                    {{ $t->pengumpulan_saya?->file ? 'Simpan perubahan' : 'Kirim pengumpulan' }}
                                                </button>
                                                <button type="button" wire:click="cancelSubmit" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50">
                                                    Batal
                                                </button>
                                            </div>
                                        </form>
                                    @elseif (! $t->terbuka)
                                        <p class="mt-2 text-xs text-amber-700">
                                            {{ $t->tanggal_mulai && $t->tanggal_mulai->isFuture() ? 'Pengumpulan belum dibuka.' : 'Masa pengumpulan telah berakhir.' }}
                                        </p>
                                    @else
                                        <button type="button" wire:click="startSubmit({{ $t->id }})" class="mt-2 inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold text-sky-700 shadow-border transition hover:bg-sky-50">
                                            <i data-lucide="upload" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                            {{ $t->pengumpulan_saya ? 'Perbarui pengumpulan' : 'Kumpulkan tugas' }}
                                        </button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    @if ($tab === 'kehadiran')
        <section class="overflow-hidden rounded-2xl bg-white shadow-border">
            <div class="border-b border-neutral-100 bg-neutral-50 px-5 py-3">
                <h2 class="text-base font-semibold text-neutral-900">Kehadiran Anda</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Rekap status kehadiran per sesi perkuliahan pada slot jadwal ini.</p>
            </div>
            <div class="overflow-x-auto p-4 sm:p-5">
                @if ($this->perkuliahanRows->isEmpty())
                    <p class="py-6 text-center text-sm text-neutral-600">Belum ada data perkuliahan; kehadiran akan muncul setelah ada sesi.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-200 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <th class="px-3 py-2">No</th>
                                <th class="px-3 py-2">Pertemuan</th>
                                <th class="px-3 py-2">Tanggal</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($this->perkuliahanRows as $idx => $p)
                                <tr wire:key="kehadiran-{{ $p->id }}" class="text-neutral-800">
                                    <td class="px-3 py-2.5 text-neutral-600">{{ $idx + 1 }}</td>
                                    <td class="px-3 py-2.5 font-medium">Ke-{{ $p->pertemuan_ke }}</td>
                                    <td class="px-3 py-2.5 text-neutral-600">{{ $p->tanggal ? \Carbon\Carbon::parse($p->tanggal)->translatedFormat('d M Y') : '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeKehadiranClass($p->kehadiran_saya?->status) }}">
                                            {{ $labelKehadiran($p->kehadiran_saya?->status) }}
                                        </span>
                                    </td>
                                    <td class="max-w-[200px] px-3 py-2.5 text-neutral-600">{{ $p->kehadiran_saya?->keterangan ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    @endif
</div>
