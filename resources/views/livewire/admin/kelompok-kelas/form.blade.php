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

@section('title', ($kelompokKelasId ? 'Ubah' : 'Tambah') . ' Kelas Mahasiswa — ' . config('app.name'))
@section('header_title', ($kelompokKelasId ? 'Ubah' : 'Tambah') . ' Kelas Mahasiswa')
@section('header_icon', 'users-round')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Mahasiswa'],
        ['label' => 'Kelas Mahasiswa', 'route' => route('admin.administrasi.kelas-mahasiswa')],
        ['label' => $kelompokKelasId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Kelas Mahasiswa *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Prodi</label>
                    <x-searchable-select
                        model="id_prodi"
                        :options="$prodiOptions"
                        placeholder="— Pilih prodi —"
                    />
                    @error('id_prodi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan</label>
                    <input type="text" wire:model="keterangan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('keterangan') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('keterangan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.administrasi.kelas-mahasiswa') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>

    @if ($kelompokKelasId)
        <div class="mt-6 rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-neutral-900">Mahasiswa dalam kelas mahasiswa ini</h2>
                    <p class="text-sm text-neutral-500">Daftar mahasiswa yang terdaftar pada kelas mahasiswa ini. Gunakan filter untuk mempersempit.</p>
                </div>
                <button
                    type="button"
                    wire:click="openAddMahasiswaModal"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah Mahasiswa
                </button>
            </div>

            <div class="mb-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Program Studi</label>
                    <x-searchable-select
                        model="mhsFilterProdi"
                        :live="true"
                        :options="$this->prodiFilterOptions"
                        placeholder="Semua prodi"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester Masuk</label>
                    <x-searchable-select
                        model="mhsFilterSemester"
                        :live="true"
                        :options="$this->semesterFilterOptions"
                        placeholder="Semua semester masuk"
                    />
                </div>
            </div>

            @php $mhsList = $this->mahasiswaInGroup; @endphp

            <div class="overflow-x-auto rounded-xl shadow-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">NIM</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Prodi</th>
                            <th class="px-4 py-3">Semester Masuk</th>
                            <th class="px-4 py-3">Status Akademik</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($mhsList as $mhs)
                            <tr wire:key="mhs-group-{{ $mhs->id }}">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $mhs->nim ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $mhs->nama }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $mhs->prodi->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $mhs->semester_masuk->nama ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($mhs->status_akademik->nama ?? null) }}">
                                        {{ $mhs->status_akademik->nama ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="confirmRemoveMahasiswa({{ $mhs->id }})"
                                        title="Keluarkan dari kelas mahasiswa"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                    >
                                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-neutral-500">Tidak ada mahasiswa untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $mhsList->links() }}
            </div>
        </div>

        {{-- Modal: Tambah Mahasiswa --}}
        @if ($showAddMahasiswaModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-border-lg">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-neutral-900">Tambah mahasiswa ke kelas mahasiswa</h3>
                        <button type="button" wire:click="closeAddMahasiswaModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                            <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                        </button>
                    </div>

                    <form wire:submit="addMahasiswaToKelompok" class="space-y-4 p-6">
                        <p class="text-sm text-neutral-500">Cari berdasarkan NIM atau nama (minimal 2 karakter), lalu simpan.</p>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mahasiswa</label>

                            @if ($selectedMahasiswaId)
                                <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                                    <span class="font-medium text-neutral-900">{{ $selectedMahasiswaLabel }}</span>
                                    <button type="button" wire:click="$set('selectedMahasiswaId', null)" class="text-neutral-400 transition hover:text-neutral-600">
                                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @else
                                <div class="relative">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="mahasiswaSearch"
                                        placeholder="Ketik NIM atau nama..."
                                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                    />
                                    @if ($mahasiswaSearch !== '')
                                        <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                            @forelse ($this->mahasiswaSearchResults as $m)
                                                <button
                                                    type="button"
                                                    wire:click="selectMahasiswaOption({{ $m->id }}, '{{ addslashes(trim(($m->nim ?? '').' — '.$m->nama)) }}')"
                                                    class="block w-full px-3 py-2 text-left text-sm transition hover:bg-neutral-50"
                                                >
                                                    <span class="font-medium text-neutral-900">{{ $m->nim ?? '—' }}</span>
                                                    <span class="text-neutral-500"> — {{ $m->nama }}</span>
                                                </button>
                                            @empty
                                                <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil.</p>
                                            @endforelse
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-3 border-t border-neutral-200 pt-4">
                            <button type="button" wire:click="closeAddMahasiswaModal" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                                Batal
                            </button>
                            <button type="submit" @disabled(! $selectedMahasiswaId) class="flex-1 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60">
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Modal: Konfirmasi Keluarkan Mahasiswa --}}
        @if ($confirmingRemoveMahasiswaId)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                    <h3 class="text-base font-semibold text-neutral-900">Keluarkan dari kelas mahasiswa?</h3>
                    <p class="mt-2 text-sm text-neutral-600">Mahasiswa akan dikeluarkan dari kelas mahasiswa ini. Data mahasiswa tidak dihapus.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="cancelRemoveMahasiswa" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                            Batal
                        </button>
                        <button type="button" wire:click="removeMahasiswaFromKelompok" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                            Keluarkan
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
