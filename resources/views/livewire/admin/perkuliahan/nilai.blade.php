@php
    $kelas = $this->kelas;
    $matkul = $kelas->kurikulumMatkul?->matkul;
    $matkulLabel = trim(($matkul?->kode ? "{$matkul->kode} — " : '') . ($matkul?->nama ?? 'Nilai'));
@endphp

@section('title', 'Nilai — ' . $matkulLabel . ' — ' . config('app.name'))
@section('header_title', 'Rincian Nilai')
@section('header_subtitle', $matkulLabel)
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Perkuliahan', 'route' => route('admin.akademik.perkuliahan')],
        ['label' => 'Nilai'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Informasi Kelas</h2>
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mata Kuliah</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $matkul?->kode ? "{$matkul->kode} — " : '' }}{{ $matkul?->nama ?? '—' }}</dd>
            </div>
            @if ($kelas->kode)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kode Kelas</dt>
                    <dd class="mt-0.5 text-neutral-900">{{ $kelas->kode }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $kelas->prodi ? ($kelas->prodi->jenjang?->kode ? "{$kelas->prodi->nama} ({$kelas->prodi->jenjang->kode})" : $kelas->prodi->nama) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $kelas->semester ? "{$kelas->semester->nama} ({$kelas->semester->kode})" : '—' }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Nilai Mahasiswa</h2>

        @if ($this->mahasiswaList->isEmpty())
            <p class="text-sm text-neutral-500">Belum ada mahasiswa yang mengambil kelas ini.</p>
        @else
            <div class="overflow-x-auto rounded-lg shadow-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">NIM</th>
                            <th class="px-4 py-3">Nama</th>
                            @foreach ($this->jenisPenilaian as $jp)
                                <th class="px-3 py-3 text-center">
                                    {{ $jp->nama }}
                                    <div class="font-normal normal-case text-neutral-400">({{ $jp->bobot }}%)</div>
                                </th>
                            @endforeach
                            <th class="px-3 py-3 text-center">Angka Mutu</th>
                            <th class="px-3 py-3 text-center">Huruf Mutu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->mahasiswaList as $idx => $mhs)
                            <tr wire:key="nilai-{{ $mhs->id_krs }}">
                                <td class="px-4 py-3 text-neutral-600">{{ $idx + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $mhs->nim }}</td>
                                <td class="px-4 py-3 text-neutral-900">{{ $mhs->nama }}</td>
                                @foreach ($this->jenisPenilaian as $jp)
                                    @php $komponen = $mhs->nilai_komponen->get($jp->id); @endphp
                                    <td class="px-3 py-3 text-center text-neutral-900">
                                        {{ $komponen ? $komponen->nilai : '—' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-3 text-center font-semibold text-neutral-900">
                                    {{ $mhs->nilai?->angka_mutu ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-neutral-900">
                                    {{ $mhs->nilai?->huruf_mutu ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
