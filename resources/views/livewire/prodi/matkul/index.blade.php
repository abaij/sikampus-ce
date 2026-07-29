@section('title', 'Mata Kuliah — ' . config('app.name'))
@section('header_title', 'Mata Kuliah')
@section('header_subtitle', 'Data master mata kuliah program studi Anda')

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari kode atau nama mata kuliah..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                    <x-searchable-select
                        model="filterProdi"
                        :live="true"
                        :options="$prodiOptions"
                        optionLabel="label"
                        placeholder="Semua prodi"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="array_combine(range(1, 8), range(1, 8))"
                        placeholder="Semua"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status</label>
                    <x-searchable-select
                        model="filterStatus"
                        :live="true"
                        :options="['active' => 'Aktif', 'inactive' => 'Tidak Aktif']"
                        placeholder="Semua"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">SKS</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($matkulList as $matkul)
                        <tr wire:key="matkul-{{ $matkul->id }}">
                            <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $matkul->kode }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $matkul->nama }}</div>
                                @if ($matkul->nama_en)
                                    <div class="text-xs text-neutral-500">{{ $matkul->nama_en }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $matkul->prodi ? $matkul->prodi->nama . ($matkul->prodi->jenjang?->kode ? " ({$matkul->prodi->jenjang->kode})" : '') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $matkul->sks ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $matkul->semester ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $matkul->jenisMatkul ? ($matkul->jenisMatkul->kode ? "{$matkul->jenisMatkul->nama} ({$matkul->jenisMatkul->kode})" : $matkul->jenisMatkul->nama) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $matkul->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-700' }}">
                                    {{ $matkul->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('prodi.matkul.show', $matkul->id) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Belum ada data mata kuliah.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $matkulList->links() }}
        </div>
    </div>
</div>
