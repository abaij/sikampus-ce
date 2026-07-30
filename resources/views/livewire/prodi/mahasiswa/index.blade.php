@section('title', 'Mahasiswa — ' . config('app.name'))
@section('header_title', 'Mahasiswa')
@section('header_subtitle', 'Data mahasiswa program studi Anda')

@php
    $statusBadgeClass = function (?string $nama) {
        $nama = mb_strtolower(trim((string) $nama));

        return match (true) {
            $nama === '' => 'bg-neutral-100 text-neutral-600',
            str_contains($nama, 'aktif') => 'bg-emerald-50 text-emerald-700',
            str_contains($nama, 'cuti') => 'bg-amber-50 text-amber-700',
            str_contains($nama, 'lulus') => 'bg-blue-50 text-blue-700',
            str_contains($nama, 'dropout') => 'bg-rose-50 text-rose-700',
            default => 'bg-neutral-100 text-neutral-600',
        };
    };
@endphp

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari NIM, nama, atau email..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester Masuk</label>
                    <x-searchable-select
                        model="filterSemesterMasuk"
                        :live="true"
                        :options="$this->semesterOptions"
                        placeholder="Semua"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kelompok Kelas</label>
                    <x-searchable-select
                        model="filterKelompokKelas"
                        :live="true"
                        :options="$this->kelompokKelasOptions"
                        placeholder="Semua"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status Akademik</label>
                    <x-searchable-select
                        model="filterStatusAkademik"
                        :live="true"
                        :options="$this->statusAkademikOptions"
                        placeholder="Semua"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Semester Masuk</th>
                        <th class="px-4 py-3">Kelompok Kelas</th>
                        <th class="px-4 py-3">Status Akademik</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($mahasiswaList as $mahasiswa)
                        <tr wire:key="mahasiswa-{{ $mahasiswa->id }}">
                            <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $mahasiswa->nim ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $mahasiswa->nama }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $mahasiswa->prodi?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $mahasiswa->semester_masuk?->nama ?? $mahasiswa->semester_masuk?->kode ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $mahasiswa->kelompok_kelas?->nama ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($mahasiswa->status_akademik?->nama)
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadgeClass($mahasiswa->status_akademik->nama) }}">
                                        {{ $mahasiswa->status_akademik->nama }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('prodi.mahasiswa.show', $mahasiswa->id) }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat detail mahasiswa"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada data mahasiswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $mahasiswaList->links() }}
        </div>
    </div>
</div>
