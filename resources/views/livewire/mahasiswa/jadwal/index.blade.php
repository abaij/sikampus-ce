@section('title', 'Jadwal Kuliah — ' . config('app.name'))
@section('header_title', 'Jadwal Kuliah')
@section('header_subtitle', 'Pilih semester akademik, lalu pilih mata kuliah / kelas yang Anda kontrak untuk melihat jadwal pertemuannya.')

@php
    $sesiBadgeClass = function (?string $status) {
        return match ($status) {
            'sedang_berlangsung' => 'bg-sky-100 text-sky-800',
            'selesai' => 'bg-emerald-100 text-emerald-800',
            'belum_mulai' => 'bg-neutral-100 text-neutral-700',
            default => 'bg-neutral-50 text-neutral-500',
        };
    };
    $kelasOptions = $this->kelasOptions;
    $jadwalList = $this->jadwalList;
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <div class="w-full sm:w-64">
            <label class="mb-1.5 block text-xs font-medium text-neutral-500">Semester</label>
            <x-searchable-select
                model="filterSemester"
                :options="$this->semesterOptions"
                :live="true"
                :clearable="false"
                placeholder="Pilih semester"
            />
        </div>
        <div class="w-full sm:w-96">
            <label class="mb-1.5 block text-xs font-medium text-neutral-500">Kelas yang dikontrak</label>
            <x-searchable-select
                model="filterKelas"
                :options="$kelasOptions"
                :live="true"
                placeholder="{{ count($kelasOptions) === 0 ? 'Tidak ada kelas pada semester ini' : 'Pilih kelas / mata kuliah' }}"
            />
        </div>
    </div>

    @if (count($kelasOptions) === 0)
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="calendar" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada kelas yang dikontrak</p>
            <p class="mt-1 text-sm text-neutral-500">Anda belum memiliki KRS untuk semester ini.</p>
        </div>
    @elseif ($filterKelas === '')
        <div class="rounded-2xl bg-amber-50 p-10 text-center shadow-border">
            <i data-lucide="book-open" class="mx-auto h-10 w-10 text-amber-500" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-800">Pilih kelas terlebih dahulu</p>
            <p class="mt-1 text-sm text-neutral-600">Gunakan dropdown "Kelas yang dikontrak" di atas untuk menampilkan jadwal.</p>
        </div>
    @elseif ($jadwalList->isEmpty())
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="calendar" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Belum ada slot jadwal</p>
            <p class="mt-1 text-sm text-neutral-500">Untuk kelas yang dipilih belum ada jadwal pertemuan yang terdaftar.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-border">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Hari</th>
                            <th class="px-4 py-3">Jam</th>
                            <th class="px-4 py-3">Mata Kuliah</th>
                            <th class="px-4 py-3 text-center">SKS</th>
                            <th class="px-4 py-3">Dosen</th>
                            <th class="px-4 py-3">Ruangan</th>
                            <th class="px-4 py-3 text-center">Jenis</th>
                            <th class="px-4 py-3 text-center">Status pertemuan</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($jadwalList as $idx => $item)
                            <tr wire:key="jadwal-{{ $item->id }}">
                                <td class="px-4 py-3 text-neutral-600">{{ $idx + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ ucfirst((string) $item->hari) }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ substr((string) $item->jam_mulai, 0, 5) }} - {{ substr((string) $item->jam_selesai, 0, 5) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">{{ $item->matkul->kode ?? '-' }}</span>
                                    <div class="mt-0.5 font-medium text-neutral-900">{{ $item->matkul->nama ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center text-neutral-600">{{ $item->matkul->sks ?? '-' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $item->dosen->isNotEmpty() ? $item->dosen->pluck('nama')->implode(', ') : '-' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $item->ruangan->nama ?? '-' }}</td>
                                <td class="px-4 py-3 text-center text-neutral-600">{{ $item->jenis_kuliah->nama ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $sesiBadgeClass($item->sesi_status) }}">
                                        {{ $item->sesi_status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a
                                        href="{{ route('mahasiswa.jadwal.detail', $item->id) }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 shadow-border transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Detail jadwal"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
