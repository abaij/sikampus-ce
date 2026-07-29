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
                <tr wire:key="kehadiran-rekap-{{ $mhs->id_mahasiswa }}">
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
