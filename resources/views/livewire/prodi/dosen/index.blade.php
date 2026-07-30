@section('title', 'Dosen — ' . config('app.name'))
@section('header_title', 'Dosen')
@section('header_subtitle', 'Data dosen institusi')

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="border-b border-neutral-200 p-4">
            <div class="relative max-w-md">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama atau kode dosen..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">NIP</th>
                        <th class="px-4 py-3">NIDN</th>
                        <th class="px-4 py-3">No. HP</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($dosenList as $dosen)
                        <tr wire:key="dosen-{{ $dosen->id }}">
                            <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $dosen->kode_dosen ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-900">
                                {{ trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : '')) }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->nip ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->nidn ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->no_hp ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('prodi.dosen.show', $dosen->id) }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat detail dosen"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada data dosen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $dosenList->links() }}
        </div>
    </div>
</div>
