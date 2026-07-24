@php
    $dosen = $this->dosen;
    $namaLengkap = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));
    $initials = collect(explode(' ', trim($dosen->nama)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') ?: '?';
    $avatarUrl = $dosen->foto ? asset('storage/'.ltrim($dosen->foto, '/')) : null;
@endphp

@section('title', 'Detail Dosen — ' . config('app.name'))
@section('header_title', 'Detail Dosen')
@section('header_subtitle', $namaLengkap)
@section('header_icon', 'user-round')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Dosen', 'route' => route('admin.administrasi.dosen')],
        ['label' => $namaLengkap],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.administrasi.dosen') }}"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
    <a
        href="{{ route('admin.administrasi.dosen.edit', $dosen->id) }}"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
    >
        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
        Ubah
    </a>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-slate-200">
        <nav class="-mb-px flex flex-wrap gap-6">
            @foreach ([['key' => 'biodata', 'label' => 'Biodata'], ['key' => 'kelas', 'label' => 'Kelas Kuliah'], ['key' => 'bimbingan', 'label' => 'Mahasiswa Bimbingan']] as $tab)
                <button
                    type="button"
                    wire:click="setTab('{{ $tab['key'] }}')"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition {{ $activeTab === $tab['key'] ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab: Biodata --}}
    @if ($activeTab === 'biodata')
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col items-center gap-6 border-b border-slate-200 pb-6 sm:flex-row sm:items-start">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $dosen->nama }}" class="h-28 w-28 shrink-0 rounded-full object-cover ring-2 ring-slate-200" />
                    @else
                        <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-3xl font-semibold text-indigo-600 ring-2 ring-indigo-100">
                            {{ $initials }}
                        </div>
                    @endif
                    <div class="min-w-0 text-center sm:text-left">
                        <h2 class="text-xl font-semibold text-slate-900">{{ $namaLengkap }}</h2>
                        @if ($dosen->kode_dosen)
                            <p class="mt-1 text-sm text-slate-500">Kode: {{ $dosen->kode_dosen }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="mb-4 text-sm font-semibold text-slate-900">Informasi Personal</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Email</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->email ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">NIP</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->nip ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">NIDN</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->nidn ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">NIDK</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->nidk ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">NUPN</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->nupn ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Jenis Kelamin</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">
                                {{ $dosen->jenis_kelamin === 'L' ? 'Laki-laki' : ($dosen->jenis_kelamin === 'P' ? 'Perempuan' : '—') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Tempat, Tanggal Lahir</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">
                                {{ $dosen->tempat_lahir ?? '—' }}{{ $dosen->tanggal_lahir ? ', '.$dosen->tanggal_lahir->translatedFormat('d F Y') : '' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">No. HP</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->no_hp ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Kuota Bimbingan Akademik</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->kuota_bimbingan_akademik ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Kuota Bimbingan TA</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->kuota_bimbingan_ta ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-6">
                    <h3 class="mb-4 text-sm font-semibold text-slate-900">Alamat</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold uppercase text-slate-500">Alamat</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->alamat ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Kode Pos</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->kode_pos ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Kota</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->kota->nama ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Provinsi</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->provinsi->nama ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Negara</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $dosen->negara->nama ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-200 pt-6">
                    <button
                        type="button"
                        wire:click="confirmDeleteDosen"
                        class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-100"
                    >
                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                        Hapus Dosen
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab: Kelas Kuliah --}}
    @if ($activeTab === 'kelas')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Kelas diampu</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Daftar dari <code class="rounded bg-slate-100 px-1">kelas_dosen</code> — kelas yang di-assign ke dosen ini.
                    </p>
                </div>
                <div class="w-full sm:w-64">
                    <x-searchable-select
                        model="selectedSemester"
                        :live="true"
                        :options="$this->semesterOptions->mapWithKeys(fn ($opt) => [$opt->id => $opt->nama.($opt->is_active ? ' (Aktif)' : '')])->all()"
                        placeholder="Semua semester"
                    />
                </div>
            </div>

            @php $kelasDiampu = $this->kelasDiampu; @endphp

            @if ($kelasDiampu->isEmpty())
                <div class="py-12 text-center text-slate-500">Belum ada kelas pada semester ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Mata Kuliah</th>
                                <th class="px-4 py-3">Kurikulum</th>
                                <th class="px-4 py-3">Prodi</th>
                                <th class="px-4 py-3">Semester</th>
                                <th class="px-4 py-3">Angkatan</th>
                                <th class="px-4 py-3">Kode Kelas</th>
                                <th class="px-4 py-3">Kelompok</th>
                                <th class="px-4 py-3">Kuota</th>
                                <th class="px-4 py-3">Aktif</th>
                                <th class="px-4 py-3">PIC</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($kelasDiampu as $row)
                                @php $k = $row->kelas; $matkul = $k?->kurikulumMatkul?->matkul; $kurikulum = $k?->kurikulumMatkul?->kurikulum; @endphp
                                <tr wire:key="kelas-{{ $row->id }}">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $matkul->kode ?? '—' }} — {{ $matkul->nama ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $kurikulum ? $kurikulum->kode.' · '.$kurikulum->nama : '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $k?->prodi?->nama ?? '—' }}
                                        @if ($k?->prodi?->jenjang)
                                            <div class="text-xs text-slate-400">{{ $k->prodi->jenjang->nama }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $k?->semester ? $k->semester->nama.' ('.$k->semester->kode.')' : '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $k?->angkatan ? $k->angkatan->nama.' ('.$k->angkatan->kode.')' : '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $k?->kode ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $k?->kelompokKelas?->nama ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $k?->kuota ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $k?->is_active ? 'Ya' : 'Tidak' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $row->is_pic ? 'Ya' : 'Tidak' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Tab: Mahasiswa Bimbingan --}}
    @if ($activeTab === 'bimbingan')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-semibold text-slate-900">Daftar Mahasiswa Bimbingan</h3>
                <button
                    type="button"
                    wire:click="openModal"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah Mahasiswa
                </button>
            </div>

            <div class="relative mb-4">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="bimbinganSearch"
                    placeholder="Cari nama atau NIM..."
                    class="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                />
            </div>

            @php $bimbinganList = $this->bimbinganList; @endphp

            @if ($bimbinganList->isEmpty())
                <div class="py-12 text-center text-slate-500">
                    {{ $bimbinganSearch ? 'Tidak ada hasil pencarian.' : 'Belum ada mahasiswa bimbingan.' }}
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">NIM</th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Prodi</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($bimbinganList as $item)
                                <tr wire:key="bimbingan-{{ $item->id }}">
                                    <td class="px-4 py-3 text-slate-600">{{ $item->mahasiswa->nim ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">{{ $item->mahasiswa->nama ?? '—' }}</div>
                                        @if ($item->mahasiswa?->email)
                                            <div class="text-xs text-slate-500">{{ $item->mahasiswa->email }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $item->mahasiswa->prodi->nama ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $item->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $item->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button type="button" wire:click="openModal({{ $item->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" title="Ubah">
                                                <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" wire:click="confirmDeleteBimbingan({{ $item->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700" title="Hapus">
                                                <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $bimbinganList->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Modal: Tambah/Ubah Bimbingan --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">
                        {{ $editingBimbinganId ? 'Ubah Bimbingan' : 'Tambah Mahasiswa Bimbingan' }}
                    </h3>
                    <button type="button" wire:click="closeModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <form wire:submit="saveBimbingan" class="space-y-4 p-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Mahasiswa *</label>

                        @if ($selectedMahasiswaId)
                            <div class="flex items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm">
                                <span class="font-medium text-slate-900">{{ $selectedMahasiswaLabel }}</span>
                                <button type="button" wire:click="$set('selectedMahasiswaId', null)" class="text-slate-400 transition hover:text-slate-600">
                                    <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                                </button>
                            </div>
                        @else
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="mahasiswaSearch"
                                    placeholder="Cari NIM atau nama mahasiswa..."
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('selectedMahasiswaId') border-red-500 @enderror"
                                />
                                @if ($mahasiswaSearch !== '')
                                    <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                        @forelse ($this->mahasiswaResults as $m)
                                            <button
                                                type="button"
                                                wire:click="selectMahasiswa({{ $m->id }}, '{{ addslashes(trim(($m->nim ?? '').' - '.$m->nama)) }}')"
                                                class="block w-full px-3 py-2 text-left text-sm transition hover:bg-slate-50"
                                            >
                                                <span class="font-medium text-slate-900">{{ $m->nim ?? '—' }}</span>
                                                <span class="text-slate-500"> — {{ $m->nama }}</span>
                                            </button>
                                        @empty
                                            <p class="px-3 py-2 text-sm text-slate-500">Tidak ada hasil.</p>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('selectedMahasiswaId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                        <x-searchable-select
                            model="bimbinganStatus"
                            :clearable="false"
                            :options="['active' => 'Aktif', 'inactive' => 'Tidak Aktif']"
                        />
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
                        <button type="button" wire:click="closeModal" class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Batal
                        </button>
                        <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Hapus Bimbingan --}}
    @if ($confirmingBimbinganDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">Hapus data bimbingan?</h3>
                <p class="mt-2 text-sm text-slate-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteBimbingan" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteBimbingan" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Hapus Dosen --}}
    @if ($confirmingDosenDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-slate-900">Hapus dosen ini?</h3>
                <p class="mt-2 text-sm text-slate-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteDosen" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteDosen" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
