@php
    $jadwal = $this->jadwal;
    $matkul = $jadwal->kelas?->kurikulumMatkul?->matkul;
    $matkulLabel = trim(($matkul?->kode ? "{$matkul->kode} — " : '') . ($matkul?->nama ?? 'Jadwal'));
    $sesiClass = match ($jadwal->sesi_status) {
        'sedang_berlangsung' => 'bg-sky-50 text-sky-700',
        'selesai' => 'bg-emerald-50 text-emerald-700',
        default => 'bg-neutral-100 text-neutral-600',
    };
@endphp

@section('title', $matkulLabel . ' — ' . config('app.name'))
@section('header_title', $matkulLabel)
@section('header_subtitle', 'Detail jadwal pertemuan' . ($jadwal->urutan_pertemuan !== null ? " · Pertemuan ke-{$jadwal->urutan_pertemuan}" : ''))
@section('header_icon', 'calendar-clock')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Jadwal', 'route' => route('admin.akademik.jadwal')],
        ['label' => 'Detail'],
    ]])
@endsection

{{-- Tombol aksi sengaja berada di dalam badan komponen, bukan di section page_actions:
     layouts.web me-render page_actions di luar root <div> komponen, sehingga wire:click di sana
     tidak pernah terikat Livewire (tombol tampil tapi diam saat diklik). --}}
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
        <a
            href="{{ route('admin.akademik.jadwal.edit', $jadwalId) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
        <button
            type="button"
            wire:click="confirmDelete"
            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-600 shadow-border transition hover:bg-rose-50"
        >
            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
            Hapus
        </button>
    </div>

    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold text-neutral-900">Informasi Jadwal</h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $sesiClass }}">
                {{ $jadwal->sesi_status_label }}
            </span>
        </div>
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mata Kuliah</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $matkul?->kode ? "{$matkul->kode} — " : '' }}{{ $matkul?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $jadwal->kelas?->prodi ? ($jadwal->kelas->prodi->jenjang?->kode ? "{$jadwal->kelas->prodi->nama} ({$jadwal->kelas->prodi->jenjang->kode})" : $jadwal->kelas->prodi->nama) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester Kelas</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $jadwal->kelas?->semester ? "{$jadwal->kelas->semester->nama} ({$jadwal->kelas->semester->kode})" : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Pertemuan</dt>
                <dd class="mt-0.5 tabular-nums text-neutral-900">{{ $jadwal->urutan_pertemuan !== null ? "Ke-{$jadwal->urutan_pertemuan}" : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Status Aktif</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $jadwal->is_active ? 'Ya' : 'Tidak' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jenis Kuliah</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $jadwal->jenisKuliah?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Hari / Tanggal</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $jadwal->hari ? ucfirst($jadwal->hari) : '—' }}
                    {{ $jadwal->tanggal ? ' · ' . $jadwal->tanggal->format('d M Y') : '' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jam</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $jadwal->jam_mulai && $jadwal->jam_selesai ? "{$jadwal->jam_mulai} – {$jadwal->jam_selesai}" : ($jadwal->jam_mulai ?? $jadwal->jam_selesai ?? '—') }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ruangan</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $jadwal->ruangan?->nama ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Dosen Pengajar</dt>
                <dd class="mt-0.5 text-neutral-900">
                    @if ($jadwal->dosen->isNotEmpty())
                        {{ $jadwal->dosen->map(fn ($jd) => $jd->dosen ? trim($jd->dosen->nama . ($jd->dosen->kode_dosen ? " ({$jd->dosen->kode_dosen})" : '')) : null)->filter()->join(', ') }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($jadwal->bahasan)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Bahasan</dt>
                    <dd class="mt-0.5 whitespace-pre-wrap text-neutral-900">{{ $jadwal->bahasan }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Sesi Perkuliahan</h2>
        @if ($this->perkuliahanList->isEmpty())
            <p class="text-sm text-neutral-500">Belum ada sesi perkuliahan untuk jadwal ini.</p>
        @else
            <div class="overflow-x-auto rounded-lg shadow-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Waktu Mulai</th>
                            <th class="px-4 py-3">Waktu Selesai</th>
                            <th class="px-4 py-3">Materi</th>
                            <th class="px-4 py-3">Realisasi</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->perkuliahanList as $p)
                            @php
                                $pClass = match ($p->sesi_status) {
                                    'sedang_berlangsung' => 'bg-sky-50 text-sky-700',
                                    'selesai' => 'bg-emerald-50 text-emerald-700',
                                    default => 'bg-neutral-100 text-neutral-600',
                                };
                            @endphp
                            <tr wire:key="perkuliahan-{{ $p->id }}" class="{{ $p->is_aktif ? 'bg-sky-50/50' : '' }}">
                                <td class="px-4 py-3 text-neutral-900">
                                    {{ $p->tanggal ? \Illuminate\Support\Carbon::parse($p->tanggal)->format('d M Y') : '—' }}
                                    @if ($p->is_aktif)
                                        <span class="ml-1 text-xs font-medium text-sky-700">(sesi utama)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-neutral-900">{{ $p->waktu_mulai ? \Illuminate\Support\Carbon::parse($p->waktu_mulai)->format('d M Y H:i') : '—' }}</td>
                                <td class="px-4 py-3 text-neutral-900">{{ $p->waktu_selesai ? \Illuminate\Support\Carbon::parse($p->waktu_selesai)->format('d M Y H:i') : '—' }}</td>
                                <td class="max-w-xs px-4 py-3 text-neutral-900">
                                    <span class="line-clamp-2">{{ $p->materi ?: '—' }}</span>
                                </td>
                                <td class="max-w-xs px-4 py-3 text-neutral-900">
                                    <span class="line-clamp-2">{{ $p->realisasi_materi ?: '—' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pClass }}">
                                        {{ $p->sesi_status_label }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-1 text-base font-semibold text-neutral-900">Kehadiran Mahasiswa</h2>
        @if (! $this->perkuliahanAktif)
            <p class="text-sm text-neutral-500">Daftar kehadiran ditampilkan setelah ada sesi perkuliahan.</p>
        @else
            <p class="mb-4 text-sm text-neutral-500">Kehadiran pada sesi perkuliahan utama (berlangsung atau terbaru).</p>
            @if ($this->kehadiranList->isEmpty())
                <p class="text-sm text-neutral-500">Tidak ada mahasiswa terdaftar di kelas ini.</p>
            @else
                <div class="overflow-x-auto rounded-lg shadow-border">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">NIM</th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Prodi</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($this->kehadiranList as $idx => $row)
                                @php
                                    $status = $row->kehadiran?->status;
                                    $statusLabel = match ($status) {
                                        'hadir' => 'Hadir',
                                        'izin' => 'Izin',
                                        'sakit' => 'Sakit',
                                        'alfa' => 'Alfa',
                                        default => 'Belum diisi',
                                    };
                                    $statusClass = match ($status) {
                                        'hadir' => 'bg-emerald-50 text-emerald-700',
                                        'izin' => 'bg-amber-50 text-amber-700',
                                        'sakit' => 'bg-sky-50 text-sky-700',
                                        'alfa' => 'bg-rose-50 text-rose-700',
                                        default => 'bg-neutral-100 text-neutral-600',
                                    };
                                @endphp
                                <tr wire:key="kehadiran-{{ $row->id_krs }}">
                                    <td class="px-4 py-3 text-neutral-600">{{ $idx + 1 }}</td>
                                    <td class="px-4 py-3 font-medium text-neutral-900">{{ $row->mahasiswa->nim }}</td>
                                    <td class="px-4 py-3 text-neutral-900">{{ $row->mahasiswa->nama }}</td>
                                    <td class="px-4 py-3 text-neutral-600">{{ $row->mahasiswa->prodi?->nama ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-neutral-600">{{ $row->kehadiran?->keterangan ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus jadwal?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="delete" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
