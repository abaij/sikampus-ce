@section('title', 'Rekap Kehadiran — ' . config('app.name'))
@section('header_title', 'Rekap Kehadiran')
@section('header_subtitle', 'Lihat kehadiran Anda untuk setiap pertemuan pada mata kuliah yang Anda kontrak.')

@php
    $kelasOptions = $this->kelasOptions;
    $rekap = $this->rekap;

    $labelStatus = function (?string $status) {
        return match ($status) {
            'hadir' => 'Hadir',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alfa' => 'Alfa',
            default => 'Belum diisi',
        };
    };
    $badgeStatusClass = function (?string $status) {
        return match ($status) {
            'hadir' => 'bg-emerald-100 text-emerald-800',
            'izin' => 'bg-amber-100 text-amber-800',
            'sakit' => 'bg-sky-100 text-sky-800',
            'alfa' => 'bg-rose-100 text-rose-800',
            default => 'bg-neutral-100 text-neutral-600',
        };
    };
@endphp

<div class="space-y-6">
    @if (count($kelasOptions) === 0)
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="user-check" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Belum ada kelas yang dikontrak</p>
            <p class="mt-1 text-sm text-neutral-500">
                Anda belum memiliki KRS pada semester manapun. Rekap kehadiran per mata kuliah akan tersedia setelah Anda terdaftar di kelas.
            </p>
        </div>
    @else
        <div class="w-full sm:max-w-xl">
            <label class="mb-1.5 block text-xs font-medium text-neutral-500">Mata kuliah / kelas</label>
            <x-searchable-select
                model="filterKelas"
                :options="$kelasOptions"
                :live="true"
                :clearable="false"
                placeholder="Pilih kelas"
            />
        </div>

        @if ($rekap)
            <div class="rounded-2xl bg-white p-5 shadow-border">
                <h2 class="text-sm font-semibold text-neutral-900">Ringkasan</h2>
                <p class="mt-1 text-xs text-neutral-500">
                    {{ $rekap['kelas']->kurikulumMatkul->matkul->kode ?? '' }} — {{ $rekap['kelas']->kurikulumMatkul->matkul->nama ?? $rekap['kelas']->nama }}
                    @if ($rekap['kelas']->semester)
                        · {{ $rekap['kelas']->semester->nama }}
                    @endif
                </p>
                @if ($rekap['kelas']->dosenPic)
                    <p class="mt-1 text-xs text-neutral-500">Dosen: {{ $rekap['kelas']->dosenPic->nama }}</p>
                @endif

                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-xl bg-neutral-50 px-3 py-2 text-center shadow-border">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Pertemuan</dt>
                        <dd class="text-lg font-bold text-neutral-900">{{ $rekap['ringkasan']['total_pertemuan'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2 text-center shadow-border">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Hadir</dt>
                        <dd class="text-lg font-bold text-emerald-800">{{ $rekap['ringkasan']['hadir'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2 text-center shadow-border">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Izin</dt>
                        <dd class="text-lg font-bold text-amber-900">{{ $rekap['ringkasan']['izin'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-sky-50 px-3 py-2 text-center shadow-border">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-sky-800">Sakit</dt>
                        <dd class="text-lg font-bold text-sky-900">{{ $rekap['ringkasan']['sakit'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-rose-50 px-3 py-2 text-center shadow-border">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-rose-800">Alfa</dt>
                        <dd class="text-lg font-bold text-rose-900">{{ $rekap['ringkasan']['alfa'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-neutral-50 px-3 py-2 text-center shadow-border">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-600">Belum diisi</dt>
                        <dd class="text-lg font-bold text-neutral-800">{{ $rekap['ringkasan']['belum_catat'] }}</dd>
                    </div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-border">
                <div class="border-b border-neutral-100 bg-neutral-50 px-4 py-3 sm:px-5">
                    <h2 class="text-base font-semibold text-neutral-900">Detail per pertemuan</h2>
                </div>
                <div class="overflow-x-auto">
                    @if ($rekap['pertemuan']->isEmpty())
                        <p class="px-4 py-10 text-center text-sm text-neutral-600 sm:px-5">
                            Belum ada jadwal pertemuan (perkuliahan) yang tercatat untuk kelas ini.
                        </p>
                    @else
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-neutral-200 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                    <th class="px-4 py-3 sm:px-5">No</th>
                                    <th class="px-4 py-3 sm:px-5">Pertemuan</th>
                                    <th class="px-4 py-3 sm:px-5">Tanggal</th>
                                    <th class="px-4 py-3 sm:px-5">Status</th>
                                    <th class="px-4 py-3 sm:px-5">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @foreach ($rekap['pertemuan'] as $idx => $p)
                                    <tr wire:key="pertemuan-{{ $p->id }}" class="text-neutral-800">
                                        <td class="px-4 py-3 text-neutral-600 sm:px-5">{{ $idx + 1 }}</td>
                                        <td class="px-4 py-3 font-medium sm:px-5">Ke-{{ $p->pertemuan_ke }}</td>
                                        <td class="px-4 py-3 text-neutral-600 sm:px-5">{{ $p->tanggal ? \Carbon\Carbon::parse($p->tanggal)->translatedFormat('D, d M Y') : '—' }}</td>
                                        <td class="px-4 py-3 sm:px-5">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeStatusClass($p->kehadiran?->status) }}">
                                                {{ $labelStatus($p->kehadiran?->status) }}
                                            </span>
                                        </td>
                                        <td class="max-w-xs px-4 py-3 text-neutral-600 sm:px-5">{{ $p->kehadiran?->keterangan ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
