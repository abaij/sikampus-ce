@php
    $ujian = $this->ujian;
    $matkul = $ujian->kelas?->kurikulumMatkul?->matkul;
    $matkulLabel = trim(($matkul?->kode ? "{$matkul->kode} — " : '') . ($matkul?->nama ?? 'Jadwal Ujian'));
    $minSyarat = $this->persentaseMinimumSyarat;
@endphp

@section('title', $matkulLabel . ' — ' . config('app.name'))
@section('header_title', $matkulLabel)
@section('header_subtitle', 'Detail jadwal ujian · ' . ucfirst(strtolower($ujian->jenis_ujian)))
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Jadwal Ujian', 'route' => route('admin.akademik.jadwal-ujian')],
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
            href="{{ route('admin.akademik.jadwal-ujian.edit', $ujianId) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
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
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Informasi Ujian</h2>
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mata Kuliah</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $matkul?->kode ? "{$matkul->kode} — " : '' }}{{ $matkul?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $ujian->kelas?->prodi ? ($ujian->kelas->prodi->jenjang?->kode ? "{$ujian->kelas->prodi->nama} ({$ujian->kelas->prodi->jenjang->kode})" : $ujian->kelas->prodi->nama) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $ujian->semester ? "{$ujian->semester->nama} ({$ujian->semester->kode})" : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kode Kelas</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $ujian->kelas?->kode ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kelas Mahasiswa</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $ujian->kelas?->kelompokKelas?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jenis Ujian</dt>
                <dd class="mt-0.5">
                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                        {{ ucfirst(strtolower($ujian->jenis_ujian)) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ruangan</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $ujian->ruangan?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mulai</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $ujian->tanggal_mulai?->format('d M Y H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Selesai</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $ujian->tanggal_selesai?->format('d M Y H:i') ?? '—' }}</dd>
            </div>
            @if ($minSyarat !== null)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Syarat Bayar Minimum</dt>
                    <dd class="mt-0.5 text-neutral-900">{{ number_format($minSyarat, 2, ',', '.') }}% (kode akses: {{ strtolower($ujian->jenis_ujian) }})</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-1 text-base font-semibold text-neutral-900">Peserta Ujian</h2>
        <p class="mb-4 text-sm text-neutral-500">Mahasiswa dengan KRS disetujui pada kelas ini.</p>
        @if ($this->peserta->isEmpty())
            <p class="text-sm text-neutral-500">Belum ada peserta (KRS) untuk kelas ini.</p>
        @else
            <div class="overflow-x-auto rounded-lg shadow-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">NIM</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Prodi</th>
                            <th class="px-4 py-3">Pembayaran Akademik</th>
                            <th class="px-4 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->peserta as $idx => $p)
                            <tr wire:key="peserta-{{ $p->id_krs }}">
                                <td class="px-4 py-3 text-neutral-600">{{ $idx + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $p->mahasiswa?->nim ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-900">{{ $p->mahasiswa?->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">
                                    {{ $p->mahasiswa?->prodi ? $p->mahasiswa->prodi->nama . ($p->mahasiswa->prodi->kode ? " ({$p->mahasiswa->prodi->kode})" : '') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-neutral-600 tabular-nums">
                                    {{ $p->persentase_pembayaran_akademik !== null ? number_format($p->persentase_pembayaran_akademik, 2, ',', '.') . '%' : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($p->tidak_memenuhi_syarat)
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                            Belum memenuhi persyaratan
                                        </span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus jadwal ujian?</h3>
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
