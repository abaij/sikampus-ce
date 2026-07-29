@section('title', 'Jadwal Kuliah — ' . config('app.name'))
@section('header_title', 'Jadwal Kuliah')
@section('header_subtitle', 'Daftar jadwal kuliah program studi Anda')

@php
    $hariLabel = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu'];
@endphp

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Periode Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="$semesterOptions"
                        optionLabel="label"
                        placeholder="Semua semester"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kelas</label>
                    <x-searchable-select
                        model="filterKelas"
                        :live="true"
                        :options="$kelasOptions"
                        optionLabel="label"
                        placeholder="Semua kelas"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Hari</label>
                    <x-searchable-select
                        model="filterHari"
                        :live="true"
                        :options="$hariLabel"
                        placeholder="Semua hari"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Cari Mata Kuliah (nama / kode)</label>
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                        <input
                            type="text"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Ketik nama atau kode..."
                            class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Hari</th>
                        <th class="px-4 py-3">Jam Mulai</th>
                        <th class="px-4 py-3">Jam Selesai</th>
                        <th class="px-4 py-3">Mata Kuliah / Kelas</th>
                        <th class="px-4 py-3">Ruangan</th>
                        <th class="px-4 py-3">Dosen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($jadwalList as $jadwal)
                        @php
                            $matkul = $jadwal->kelas?->kurikulumMatkul?->matkul;
                            $dosenNames = $jadwal->dosen->map(fn ($jd) => $jd->dosen?->nama)->filter()->implode(', ');
                        @endphp
                        <tr wire:key="jadwal-{{ $jadwal->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $hariLabel[strtolower((string) $jadwal->hari)] ?? $jadwal->hari ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-neutral-700">{{ $jadwal->jam_mulai ? substr((string) $jadwal->jam_mulai, 0, 5) : '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-neutral-700">{{ $jadwal->jam_selesai ? substr((string) $jadwal->jam_selesai, 0, 5) : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-neutral-900">{{ $matkul?->nama ?? $matkul?->kode ?? '—' }}</span>
                                @if ($jadwal->kelas?->semester?->kode)
                                    <span class="text-neutral-500">({{ $jadwal->kelas->semester->kode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-700">{{ $jadwal->ruangan?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $dosenNames !== '' ? $dosenNames : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada jadwal kuliah.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $jadwalList->links() }}
        </div>
    </div>
</div>
