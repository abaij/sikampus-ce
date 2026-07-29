@section('title', 'Arsip — ' . config('app.name'))
@section('header_title', 'Arsip perkuliahan')
@section('header_subtitle', 'Arsip nilai dan kehadiran kelas yang pernah Anda ampu, per semester.')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
        </div>
    </div>

    @php $rows = $this->rows; @endphp

    <div class="rounded-2xl bg-white shadow-border">
        @if (empty($rows))
            <div class="p-10 text-center">
                <i data-lucide="archive" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">Tidak ada jadwal mengajar</p>
                <p class="mt-1 text-sm text-neutral-500">Tidak ada data jadwal untuk semester yang dipilih.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">Kode mata kuliah</th>
                            <th class="px-6 py-3">Nama mata kuliah</th>
                            <th class="px-6 py-3">SKS</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($rows as $kelas)
                            @php $km = $kelas->kurikulumMatkul; @endphp
                            <tr wire:key="arsip-kelas-{{ $kelas->id }}" class="hover:bg-neutral-50/70">
                                <td class="px-6 py-4 font-medium text-neutral-900">{{ $km?->kodeMatkulLabel() ?? '-' }}</td>
                                <td class="px-6 py-4 text-neutral-800">{{ $km?->namaMatkulLabel() ?? '-' }}</td>
                                <td class="px-6 py-4 text-neutral-600">{{ $km?->sksLabel() ?? 0 }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('dosen.arsip.nilai', $kelas->id) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                                            <i data-lucide="eye" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                            Lihat nilai
                                        </a>
                                        <a href="{{ route('dosen.kehadiran.rekap', $kelas->id) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                                            <i data-lucide="clipboard-list" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                            Lihat kehadiran
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
