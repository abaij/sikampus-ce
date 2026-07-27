@php
    $kelas = $this->kelas;
    $matkul = $kelas->kurikulumMatkul?->matkul;
    $matkulLabel = trim(($matkul?->kode ? "{$matkul->kode} — " : '') . ($matkul?->nama ?? 'Perkuliahan'));
@endphp

@section('title', $matkulLabel . ' — ' . config('app.name'))
@section('header_title', $matkulLabel)
@section('header_subtitle', 'Sesi perkuliahan dan kehadiran per kelas')
@section('header_icon', 'calendar-days')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Perkuliahan', 'route' => route('admin.akademik.perkuliahan')],
        ['label' => 'Detail'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">Informasi Kelas</h2>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="openRekap"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                >
                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                    Lihat Rekap
                </button>
                <button
                    type="button"
                    wire:click="exportKehadiran"
                    wire:target="exportKehadiran"
                    class="inline-flex items-center gap-2 rounded-lg bg-sky-50 px-4 py-2 text-sm font-medium text-sky-700 shadow-border transition hover:bg-sky-100"
                >
                    <span wire:loading.remove wire:target="exportKehadiran" class="inline-flex items-center gap-2">
                        <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
                        Ekspor
                    </span>
                    <span wire:loading wire:target="exportKehadiran" class="inline-flex items-center gap-2">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                        Menyiapkan...
                    </span>
                </button>
            </div>
        </div>
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mata Kuliah</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $matkul?->kode ? "{$matkul->kode} — " : '' }}{{ $matkul?->nama ?? '—' }}</dd>
            </div>
            @if ($kelas->kode)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kode Kelas</dt>
                    <dd class="mt-0.5 text-neutral-900">{{ $kelas->kode }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $kelas->prodi ? ($kelas->prodi->jenjang?->kode ? "{$kelas->prodi->nama} ({$kelas->prodi->jenjang->kode})" : $kelas->prodi->nama) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $kelas->semester ? "{$kelas->semester->nama} ({$kelas->semester->kode})" : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Dosen Penanggung Jawab</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->dosenPic?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jumlah Mahasiswa</dt>
                <dd class="mt-0.5 tabular-nums text-neutral-900">{{ $this->jumlahMahasiswa }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Jadwal Perkuliahan</h2>
        @if ($this->jadwalList->isEmpty())
            <p class="text-sm text-neutral-500">Belum ada jadwal untuk kelas ini.</p>
        @else
            <div class="space-y-3">
                @foreach ($this->jadwalList as $jadwal)
                    @php
                        $isOpen = $openJadwal[$jadwal->id] ?? false;
                        $sesiRows = $this->perkuliahanByJadwal->get($jadwal->id) ?? collect();
                    @endphp
                    <div wire:key="jadwal-{{ $jadwal->id }}" class="overflow-hidden rounded-xl border border-neutral-200">
                        <button
                            type="button"
                            wire:click="toggleJadwal({{ $jadwal->id }})"
                            class="flex w-full flex-wrap items-center justify-between gap-3 bg-neutral-50 px-4 py-3 text-left transition hover:bg-neutral-100"
                        >
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-900">
                                    <i data-lucide="calendar" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                                    {{ $jadwal->hari ? ucfirst($jadwal->hari) : '—' }}
                                </span>
                                <span class="inline-flex items-center gap-2 text-sm text-neutral-700">
                                    <i data-lucide="clock" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                                    {{ $jadwal->jam_mulai ?? '—' }} - {{ $jadwal->jam_selesai ?? '—' }}
                                </span>
                                @if ($jadwal->ruangan)
                                    <span class="inline-flex items-center gap-2 text-sm text-neutral-700">
                                        <i data-lucide="map-pin" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                                        {{ $jadwal->ruangan->nama }}
                                    </span>
                                @endif
                                @if ($jadwal->dosen->isNotEmpty())
                                    <span class="inline-flex items-center gap-2 text-sm text-neutral-700">
                                        <i data-lucide="users" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                                        {{ $jadwal->dosen->map(fn ($jd) => $jd->dosen?->nama)->filter()->join(', ') ?: '—' }}
                                    </span>
                                @endif
                            </div>
                            <i data-lucide="{{ $isOpen ? 'chevron-up' : 'chevron-down' }}" class="h-5 w-5 shrink-0 text-neutral-500" aria-hidden="true"></i>
                        </button>

                        @if ($isOpen)
                            <div class="border-t border-neutral-200 bg-white p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <i data-lucide="book-open" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                                    <h3 class="text-sm font-semibold text-neutral-900">Sesi Perkuliahan</h3>
                                </div>
                                @if ($sesiRows->isEmpty())
                                    <p class="text-sm text-neutral-500">Belum ada sesi perkuliahan untuk jadwal ini.</p>
                                @else
                                    <div class="space-y-2">
                                        @foreach ($sesiRows as $idx => $sesi)
                                            <div wire:key="sesi-{{ $sesi->id }}" class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                                                <div class="mb-1 flex items-center gap-2 text-xs font-semibold text-neutral-500">
                                                    Pertemuan #{{ $idx + 1 }}
                                                    @if ($sesi->waktu_mulai)
                                                        <span>· {{ $sesi->waktu_mulai->format('d M Y') }}</span>
                                                    @endif
                                                </div>
                                                @if ($sesi->materi)
                                                    <p class="mb-2 text-sm text-neutral-700">{{ $sesi->materi }}</p>
                                                @endif
                                                <div class="flex items-center gap-4 text-xs">
                                                    <span class="text-neutral-500">Hadir: <span class="font-semibold text-emerald-600">{{ $sesi->jumlah_hadir }}</span></span>
                                                    <span class="text-neutral-500">Total: <span class="font-semibold text-neutral-700">{{ $sesi->jumlah_total }}</span></span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($showRekapModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-neutral-900/40 p-4">
            <div class="my-8 w-full max-w-7xl rounded-2xl bg-white shadow-border-lg">
                <div class="sticky top-0 z-10 flex items-center justify-between rounded-t-2xl border-b border-neutral-200 bg-white px-6 py-4">
                    <h3 class="text-lg font-semibold text-neutral-900">Rekap Kehadiran Mahasiswa</h3>
                    <button type="button" wire:click="closeRekap" class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="p-6">
                    @php $rekap = $this->rekap; @endphp

                    <div class="mb-4 flex flex-wrap items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-emerald-100 text-xs font-semibold text-emerald-700">H</span>
                            <span class="text-neutral-600">Hadir</span>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-amber-100 text-xs font-semibold text-amber-700">I</span>
                            <span class="text-neutral-600">Izin</span>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-sky-100 text-xs font-semibold text-sky-700">S</span>
                            <span class="text-neutral-600">Sakit</span>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-rose-100 text-xs font-semibold text-rose-700">A</span>
                            <span class="text-neutral-600">Alfa</span>
                        </span>
                    </div>

                    <div class="max-h-[600px] overflow-auto rounded-lg border border-neutral-200">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="sticky top-0 z-10 bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <tr>
                                    <th class="border-r border-neutral-200 px-4 py-3 text-left">No</th>
                                    <th class="border-r border-neutral-200 px-4 py-3 text-left">NIM</th>
                                    <th class="border-r border-neutral-200 px-4 py-3 text-left">Nama</th>
                                    @foreach ($rekap['perkuliahan'] as $p)
                                        <th class="border-r border-neutral-200 px-3 py-3 text-center last:border-r-0" title="{{ $p->tanggal }}">
                                            {{ $p->pertemuan_ke }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 bg-white">
                                @forelse ($rekap['mahasiswa'] as $idx => $mhs)
                                    <tr wire:key="rekap-{{ $mhs->id_mahasiswa }}">
                                        <td class="border border-neutral-200 px-3 py-2 text-center text-neutral-900">{{ $idx + 1 }}</td>
                                        <td class="border border-neutral-200 px-3 py-2 text-center text-neutral-900">{{ $mhs->nim }}</td>
                                        <td class="border border-neutral-200 px-4 py-2 text-left text-neutral-900">{{ $mhs->nama }}</td>
                                        @foreach ($rekap['perkuliahan'] as $p)
                                            @php
                                                $status = $mhs->kehadiran[$p->pertemuan_ke]['status'] ?? null;
                                                $label = match ($status) {
                                                    'hadir' => ['H', 'bg-emerald-100 text-emerald-700'],
                                                    'izin' => ['I', 'bg-amber-100 text-amber-700'],
                                                    'sakit' => ['S', 'bg-sky-100 text-sky-700'],
                                                    'alfa' => ['A', 'bg-rose-100 text-rose-700'],
                                                    default => [null, ''],
                                                };
                                            @endphp
                                            <td class="border border-neutral-200 px-2 py-2 text-center">
                                                @if ($label[0])
                                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded text-xs font-semibold {{ $label[1] }}">{{ $label[0] }}</span>
                                                @else
                                                    <span class="text-neutral-400">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 3 + $rekap['perkuliahan']->count() }}" class="px-4 py-8 text-center text-neutral-500">
                                            Tidak ada data mahasiswa
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
