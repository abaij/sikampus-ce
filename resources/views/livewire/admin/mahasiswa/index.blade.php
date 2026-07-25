@php
    $statusBadgeClass = function (?string $nama) {
        $nama = mb_strtolower(trim((string) $nama));
        return match (true) {
            $nama === '' => 'bg-slate-100 text-slate-600',
            str_contains($nama, 'aktif') => 'bg-emerald-50 text-emerald-700',
            str_contains($nama, 'cuti') => 'bg-amber-50 text-amber-700',
            str_contains($nama, 'lulus') => 'bg-blue-50 text-blue-700',
            str_contains($nama, 'dropout') => 'bg-rose-50 text-rose-700',
            default => 'bg-slate-100 text-slate-600',
        };
    };
@endphp

@section('title', 'Mahasiswa — ' . config('app.name'))
@section('header_title', 'Mahasiswa')
@section('header_subtitle', 'Data induk mahasiswa')
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Mahasiswa'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="space-y-4 border-b border-slate-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama, NIM, atau email..."
                    class="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Prodi</label>
                    <select wire:model.live="filterProdi" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        <option value="">Semua Prodi</option>
                        @foreach ($this->prodiOptions as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->nama }}{{ $opt->jenjang ? ' - '.$opt->jenjang->kode : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Kelas Mahasiswa</label>
                    <select wire:model.live="filterKelompokKelas" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        <option value="">Semua Kelas Mahasiswa</option>
                        @foreach ($this->kelompokKelasOptions as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Semester Masuk</label>
                    <select wire:model.live="filterSemesterMasuk" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        <option value="">Semua Semester</option>
                        @foreach ($this->semesterOptions as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Status Akademik</label>
                    <select wire:model.live="filterStatusAkademik" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        <option value="">Semua Status</option>
                        @foreach ($this->statusAkademikOptions as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Kelas Mahasiswa</th>
                        <th class="px-4 py-3">Thn Masuk</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($mahasiswaList as $mhs)
                        <tr wire:key="mhs-{{ $mhs->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $mhs->nama }}</div>
                                <div class="text-xs text-slate-500">{{ $mhs->nim ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $mhs->prodi->nama ?? '—' }}
                                @if ($mhs->prodi?->kode)
                                    <div class="text-xs text-slate-400">{{ $mhs->prodi->kode }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $mhs->kelompok_kelas->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $mhs->semester_masuk->nama ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadgeClass($mhs->status_akademik->nama ?? null) }}">
                                    {{ $mhs->status_akademik->nama ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.administrasi.mahasiswa.show', $mhs->id) }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                    title="Lihat Detail"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada data mahasiswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 p-4">
            {{ $mahasiswaList->links() }}
        </div>
    </div>
</div>
