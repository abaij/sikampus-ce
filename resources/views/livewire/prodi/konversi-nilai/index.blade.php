@section('title', 'Konversi Nilai — ' . config('app.name'))
@section('header_title', 'Konversi Nilai')
@section('header_subtitle', 'Validasi konversi nilai mahasiswa program studi Anda')

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester (tahun berlaku kurikulum)</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="$this->semesterOptions"
                        placeholder="Semua semester"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Angkatan (semester masuk)</label>
                    <x-searchable-select
                        model="filterAngkatan"
                        :live="true"
                        :options="$this->semesterOptions"
                        placeholder="Semua angkatan"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Cari NIM / nama / kode MK</label>
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                        <input
                            type="text"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Ketik untuk mencari..."
                            class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-3 py-3">NIM</th>
                        <th class="px-3 py-3">Nama</th>
                        <th class="px-3 py-3">Angkatan</th>
                        <th class="px-3 py-3">Jenis</th>
                        <th class="px-3 py-3">MK Lama</th>
                        <th class="px-3 py-3">Nilai</th>
                        <th class="px-3 py-3">MK Baru</th>
                        <th class="px-3 py-3">Nilai</th>
                        <th class="px-3 py-3 text-center">Persetujuan</th>
                        <th class="px-3 py-3 text-center">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($konversiList as $row)
                        <tr wire:key="konversi-{{ $row['id'] }}">
                            <td class="px-3 py-3 font-mono font-medium text-neutral-900">{{ $row['mahasiswa']['nim'] ?? '—' }}</td>
                            <td class="px-3 py-3 text-neutral-900">{{ $row['mahasiswa']['nama'] ?? '—' }}</td>
                            <td class="px-3 py-3 text-neutral-600">{{ $row['mahasiswa']['semester_masuk']['kode'] ?? '—' }}</td>
                            <td class="max-w-[140px] truncate px-3 py-3 text-neutral-600" title="{{ $row['jenis_konversi']['nama'] ?? '' }}">{{ $row['jenis_konversi']['nama'] ?? '—' }}</td>
                            <td class="max-w-[160px] px-3 py-3 text-neutral-700">
                                <span class="block text-xs text-neutral-500">{{ $row['kode_mk_lama'] }}</span>
                                {{ $row['nama_mk_lama'] ?? '—' }}
                                <span class="text-neutral-500">({{ $row['sks_lama'] }} SKS)</span>
                            </td>
                            <td class="px-3 py-3 font-medium tabular-nums text-neutral-900">{{ $row['nilai_lama'] ?? '—' }}</td>
                            <td class="max-w-[160px] px-3 py-3 text-neutral-700">
                                <span class="block text-xs text-neutral-500">{{ $row['kode_mk_baru'] }}</span>
                                {{ $row['nama_mk_baru'] ?? '—' }}
                                <span class="text-neutral-500">({{ $row['sks_baru'] }} SKS)</span>
                            </td>
                            <td class="px-3 py-3 font-medium tabular-nums text-neutral-900">{{ $row['nilai_baru'] ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex justify-center">
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-neutral-600">
                                        <input
                                            type="checkbox"
                                            @checked($row['is_approved'])
                                            wire:click="toggleApproval({{ $row['id'] }}, {{ $row['is_approved'] ? 'false' : 'true' }})"
                                            class="h-4 w-4 rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        Disetujui
                                    </label>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-center">
                                    <button
                                        type="button"
                                        wire:click="openDetailModal({{ $row['id'] }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                        title="Lihat detail"
                                    >
                                        <i data-lucide="arrow-right-left" class="h-4 w-4" aria-hidden="true"></i>
                                        Transfer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-neutral-500">Belum ada data konversi nilai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $konversiList->links() }}
        </div>
    </div>

    {{-- Modal: Detail Konversi Nilai + Transfer ke Nilai --}}
    @if ($detailId)
        @php $detail = $this->detailKonversiNilai; @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4 py-8">
            <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Detail Konversi Nilai</h3>
                    <button type="button" wire:click="closeDetailModal" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    @if (! $detail)
                        <p class="py-6 text-center text-sm text-neutral-500">Data konversi tidak ditemukan.</p>
                    @else
                        <dl class="space-y-3 text-sm">
                            <div class="grid grid-cols-3 gap-2 border-b border-neutral-100 pb-2">
                                <dt class="text-neutral-500">Mahasiswa</dt>
                                <dd class="col-span-2 font-medium text-neutral-900">
                                    {{ $detail['mahasiswa']['nama'] ?? '—' }}
                                    <span class="mt-0.5 block text-xs font-normal text-neutral-600">
                                        NIM {{ $detail['mahasiswa']['nim'] ?? '—' }}
                                        @if ($detail['mahasiswa']['semester_masuk']['kode'] ?? null)
                                            &middot; Angkatan {{ $detail['mahasiswa']['semester_masuk']['kode'] }}
                                        @endif
                                    </span>
                                </dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 border-b border-neutral-100 pb-2">
                                <dt class="text-neutral-500">Kurikulum</dt>
                                <dd class="col-span-2 text-neutral-800">
                                    {{ $detail['kurikulum']['nama'] ?? '—' }}
                                    <span class="mt-0.5 block text-xs text-neutral-600">
                                        @if ($detail['kurikulum']['kode'] ?? null)
                                            {{ $detail['kurikulum']['kode'] }} &middot;
                                        @endif
                                        Tahun berlaku: {{ $detail['kurikulum']['tahun_berlaku']['kode'] ?? '—' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 border-b border-neutral-100 pb-2">
                                <dt class="text-neutral-500">Jenis</dt>
                                <dd class="col-span-2 text-neutral-800">{{ $detail['jenis_konversi']['nama'] ?? '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-neutral-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Mata Kuliah Lama</p>
                                <p class="mt-1 font-medium text-neutral-900">
                                    {{ $detail['nama_mk_lama'] ?? '—' }}
                                    <span class="text-neutral-600">({{ $detail['sks_lama'] }} SKS{{ $detail['kode_mk_lama'] ? ' · '.$detail['kode_mk_lama'] : '' }})</span>
                                </p>
                                <p class="mt-1 text-neutral-700">Nilai: <span class="font-semibold tabular-nums">{{ $detail['nilai_lama'] ?? '—' }}</span></p>
                            </div>
                            <div class="rounded-xl bg-sky-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Mata Kuliah Baru</p>
                                <p class="mt-1 font-medium text-neutral-900">
                                    {{ $detail['nama_mk_baru'] ?? '—' }}
                                    <span class="text-neutral-600">({{ $detail['sks_baru'] }} SKS{{ $detail['kode_mk_baru'] ? ' · '.$detail['kode_mk_baru'] : '' }})</span>
                                </p>
                                <p class="mt-1 text-neutral-700">Nilai: <span class="font-semibold tabular-nums">{{ $detail['nilai_baru'] ?? '—' }}</span></p>
                            </div>
                            @if ($detail['nilai_krs'])
                                <div class="grid grid-cols-3 gap-2 border-b border-neutral-100 pb-2">
                                    <dt class="text-neutral-500">Nilai</dt>
                                    <dd class="col-span-2 text-neutral-800">
                                        {{ $detail['nilai_krs']['huruf_mutu'] ?? '—' }}
                                        @if ($detail['nilai_krs']['angka_mutu'] !== null)
                                            (AM {{ $detail['nilai_krs']['angka_mutu'] }})
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            <div class="grid grid-cols-3 gap-2 border-b border-neutral-100 pb-2">
                                <dt class="text-neutral-500">Keterangan</dt>
                                <dd class="col-span-2 whitespace-pre-wrap text-neutral-800">{{ $detail['keterangan'] ?: '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 border-b border-neutral-100 pb-2">
                                <dt class="text-neutral-500">Persetujuan</dt>
                                <dd class="col-span-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $detail['is_approved'] ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-100 text-neutral-700' }}">
                                        {{ $detail['is_approved'] ? 'Disetujui' : 'Belum disetujui' }}
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-4 border-t border-neutral-200 pt-4">
                            <h4 class="text-sm font-semibold text-neutral-900">Transfer ke Nilai</h4>
                            <p class="mt-1 text-xs text-neutral-500">
                                Mentransfer nilai konversi menjadi nilai perolehan. Harap diteliti kembali nilai konversi sebelum melakukan transfer.
                            </p>

                            @if ($transferError !== '')
                                <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $transferError }}</p>
                            @endif
                            @if ($transferMessage !== '')
                                <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">{{ $transferMessage }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="transferToNilai"
                                    wire:loading.attr="disabled"
                                    wire:target="transferToNilai"
                                    @disabled(! $detail['is_approved'] || $detail['id_nilai'])
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Transfer ke Nilai
                                </button>
                                @if ($detail['id_nilai'])
                                    <span class="text-xs text-neutral-500">Sudah terhubung ke nilai (id nilai: {{ $detail['id_nilai'] }})</span>
                                @elseif (! $detail['is_approved'])
                                    <span class="text-xs text-amber-700">Setujui konversi terlebih dahulu.</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
