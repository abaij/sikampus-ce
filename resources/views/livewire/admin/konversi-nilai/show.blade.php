@section('title', 'Rincian Konversi Nilai — ' . config('app.name'))
@section('header_title', 'Rincian Konversi Nilai')
@section('header_subtitle', $this->mahasiswa->nama)
@section('header_icon', 'repeat')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Konversi Nilai', 'route' => route('admin.akademik.konversi-nilai')],
        ['label' => $this->mahasiswa->nama],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.konversi-nilai') }}"
        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="flex items-start gap-3 rounded-2xl bg-white p-4 shadow-border">
            <i data-lucide="user" class="mt-0.5 h-5 w-5 shrink-0 text-neutral-400" aria-hidden="true"></i>
            <div>
                <div class="text-base font-semibold text-neutral-900">{{ $this->mahasiswa->nama }}</div>
                <div class="text-sm text-neutral-600">{{ $this->mahasiswa->nim }}</div>
                <div class="mt-1 text-sm text-neutral-700">
                    @if ($this->mahasiswa->prodi)
                        {{ $this->mahasiswa->prodi->kode ? $this->mahasiswa->prodi->kode.' · ' : '' }}{{ $this->mahasiswa->prodi->nama }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 rounded-2xl bg-white p-4 shadow-border">
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Jumlah MK</div>
                <div class="mt-1 text-xl font-bold text-neutral-900">{{ $this->ringkasan['jumlah_matkul'] }}</div>
            </div>
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-wide text-neutral-500">SKS Lama</div>
                <div class="mt-1 text-xl font-bold text-neutral-800">{{ $this->ringkasan['total_sks_lama'] }}</div>
            </div>
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-wide text-neutral-500">SKS Baru</div>
                <div class="mt-1 text-xl font-bold text-emerald-700">{{ $this->ringkasan['total_sks_baru'] }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-neutral-900">Daftar Konversi per Mata Kuliah</h2>
            <a
                href="{{ route('admin.akademik.konversi-nilai.create') }}"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 shadow-border"
            >
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Tambah Konversi
            </a>
        </div>

        @if ($this->items->isEmpty())
            <p class="rounded-xl border border-dashed border-neutral-200 bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-600">
                Belum ada baris konversi nilai untuk mahasiswa ini.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-3 py-2">Kurikulum</th>
                            <th class="px-3 py-2">Jenis</th>
                            <th class="px-3 py-2">MK Lama</th>
                            <th class="px-3 py-2 text-center">SKS</th>
                            <th class="px-3 py-2 text-center">Nilai</th>
                            <th class="px-3 py-2">MK Baru</th>
                            <th class="px-3 py-2 text-center">SKS</th>
                            <th class="px-3 py-2 text-center">Nilai</th>
                            <th class="px-3 py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->items as $row)
                            <tr wire:key="konversi-item-{{ $row->id }}" class="align-top">
                                <td class="px-3 py-2 text-neutral-800">
                                    @if ($row->kurikulum)
                                        <span class="font-medium">{{ $row->kurikulum->kode }}</span>
                                        <span class="block text-xs text-neutral-500">{{ $row->kurikulum->nama }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-neutral-700">{{ $row->jenisKonversi->nama ?? '—' }}</td>
                                <td class="px-3 py-2 text-neutral-800">
                                    <span class="font-mono text-xs font-semibold">{{ $row->kode_mk_lama ?? '—' }}</span>
                                    <span class="mt-0.5 block text-neutral-700">{{ $row->nama_mk_lama ?? '—' }}</span>
                                </td>
                                <td class="px-3 py-2 text-center text-neutral-800">{{ $row->sks_lama }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-neutral-900">{{ $row->nilai_lama }}</td>
                                <td class="px-3 py-2 text-neutral-800">
                                    <span class="font-mono text-xs font-semibold">{{ $row->kode_mk_baru ?? '—' }}</span>
                                    <span class="mt-0.5 block text-neutral-700">{{ $row->nama_mk_baru ?? '—' }}</span>
                                </td>
                                <td class="px-3 py-2 text-center text-emerald-800">{{ $row->sks_baru }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-emerald-800">{{ $row->nilai_baru }}</td>
                                <td class="max-w-[200px] px-3 py-2 text-xs text-neutral-600">
                                    {{ $row->keterangan ?? '—' }}
                                    @if ($row->created_at || $row->created_by)
                                        <div class="mt-1 border-t border-neutral-100 pt-1 text-[10px] text-neutral-400">
                                            @if ($row->created_at)
                                                <span>{{ $row->created_at->translatedFormat('d M Y H:i') }}</span>
                                            @endif
                                            @if ($row->created_by)
                                                <span>{{ $row->created_at ? ' · ' : '' }}{{ $row->created_by }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
