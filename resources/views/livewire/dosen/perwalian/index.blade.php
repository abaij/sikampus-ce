@section('title', 'Bimbingan Akademik — ' . config('app.name'))
@section('header_title', 'Bimbingan Akademik')
@section('header_subtitle', 'Daftar mahasiswa yang menjadi bimbingan akademik Anda')

<div class="space-y-4">
    <div class="w-full max-w-md">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari berdasarkan nama atau NIM..."
                class="w-full rounded-xl bg-white py-2 pr-4 pl-10 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
            />
        </div>
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        @if ($rows->isEmpty())
            <div class="p-10 text-center">
                <i data-lucide="users" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">
                    {{ $search !== '' ? 'Tidak ada mahasiswa bimbingan yang ditemukan' : 'Anda belum memiliki mahasiswa bimbingan' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">No</th>
                            <th class="px-6 py-3">NIM</th>
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-6 py-3">No. HP</th>
                            <th class="px-6 py-3">Prodi</th>
                            <th class="px-6 py-3 text-center">Angkatan</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($rows as $idx => $dosenWali)
                            @php $mhs = $dosenWali->mahasiswa; @endphp
                            <tr wire:key="dw-{{ $dosenWali->id }}" class="hover:bg-neutral-50/70">
                                <td class="px-6 py-4 text-neutral-900">{{ $rows->firstItem() + $idx }}</td>
                                <td class="px-6 py-4 font-medium text-neutral-900">{{ $mhs->nim }}</td>
                                <td class="px-6 py-4 text-neutral-900">{{ $mhs->nama }}</td>
                                <td class="px-6 py-4 text-neutral-600">{{ $mhs->handphone ?: '-' }}</td>
                                <td class="px-6 py-4 text-neutral-600">
                                    @if ($mhs->prodi)
                                        <div>{{ $mhs->prodi->nama }}</div>
                                        @if ($mhs->prodi->jenjang)
                                            <div class="text-xs text-neutral-500">{{ $mhs->prodi->jenjang->nama }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center text-neutral-600">{{ $mhs->semester_masuk?->nama ?? '-' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <a
                                        href="{{ route('dosen.perwalian.show', $mhs->id) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50"
                                    >
                                        <i data-lucide="eye" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-200 px-4 py-3">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
