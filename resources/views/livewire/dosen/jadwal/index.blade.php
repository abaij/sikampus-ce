@section('title', 'Jadwal Mengajar — ' . config('app.name'))
@section('header_title', 'Jadwal Mengajar')
@section('header_subtitle', 'Ringkasan semua slot jadwal mengajar Anda. Untuk daftar per mata kuliah, buka Kelas Mata Kuliah.')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <x-searchable-select
                model="filterSemester"
                :options="$this->semesterOptions"
                :live="true"
                placeholder="Semua semester"
            />
        </div>
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Hari</th>
                        <th class="px-4 py-3">Jam</th>
                        <th class="px-4 py-3">Mata Kuliah</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Ruangan</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->jadwalRows as $jadwalDosen)
                        @php
                            $jadwal = $jadwalDosen->jadwal;
                            $kelas = $jadwal->kelas;
                            $km = $kelas->kurikulumMatkul;
                            $jamMulai = $jadwal->jam_mulai ? substr($jadwal->jam_mulai, 0, 5) : '—';
                            $jamSelesai = $jadwal->jam_selesai ? substr($jadwal->jam_selesai, 0, 5) : null;
                        @endphp
                        <tr wire:key="jadwal-{{ $jadwalDosen->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">
                                {{ $jadwal->hari ? ucfirst($jadwal->hari) : '—' }}
                                @if ($jadwal->tanggal)
                                    <div class="text-xs font-normal text-neutral-500">{{ $jadwal->tanggal->translatedFormat('j M Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums text-neutral-700">
                                {{ $jamMulai }}{{ $jamSelesai ? " – {$jamSelesai}" : '' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $km?->namaMatkulLabel() ?? '—' }}</div>
                                @if ($km?->kodeMatkulLabel())
                                    <div class="text-xs text-neutral-500">{{ $km->kodeMatkulLabel() }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $kelas->kelompokKelas?->nama ?? '—' }}
                                @if ($kelas->prodi)
                                    <div class="text-xs text-neutral-500">
                                        {{ $kelas->prodi->nama }}{{ $kelas->prodi->jenjang?->kode ? " ({$kelas->prodi->jenjang->kode})" : '' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $jadwal->ruangan?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $jadwal->jenisKuliah?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('dosen.jadwal.detail', ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id, 'id_semester' => $filterSemester !== '' ? $filterSemester : null]) }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat detail jadwal"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada jadwal mengajar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
