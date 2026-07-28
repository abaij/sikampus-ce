@php $kategoriBiaya = $this->kategoriBiaya; @endphp

@section('title', $kategoriBiaya->nama . ' — ' . config('app.name'))
@section('header_title', $kategoriBiaya->nama)
@section('header_subtitle', 'Kategori Biaya' . ($kategoriBiaya->kode ? ' · '.$kategoriBiaya->kode : ''))
@section('header_icon', 'tags')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Kategori Biaya', 'route' => route('admin.keuangan.kategori-biaya')],
        ['label' => $kategoriBiaya->nama],
    ]])
@endsection

@section('page_actions')
    <div class="flex items-center gap-2">
        <a
            href="{{ route('admin.keuangan.kategori-biaya.edit', $kategoriBiaya->id) }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
        <a
            href="{{ route('admin.keuangan.kategori-biaya') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
    </div>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-6 inline-flex items-center gap-2 rounded-xl bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 shadow-border">
        <i data-lucide="users" class="h-4 w-4" aria-hidden="true"></i>
        {{ $kategoriBiaya->jumlah_mahasiswa }} mahasiswa terdaftar pada kategori ini
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-neutral-900">Mahasiswa dalam kategori biaya ini</h2>
        <button
            type="button"
            wire:click="openModal"
            class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Mahasiswa
        </button>
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-200 p-4">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari NIM atau nama mahasiswa..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>

        @php $mahasiswaList = $this->mahasiswaList; @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Status Akademik</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($mahasiswaList as $mahasiswa)
                        @php $semester = $mahasiswa->kategori_biaya_mahasiswa->first()?->semester; @endphp
                        <tr wire:key="mahasiswa-{{ $mahasiswa->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $mahasiswa->nim ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $mahasiswa->nama }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $mahasiswa->prodi->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $semester ? ($semester->kode ? "{$semester->nama} ({$semester->kode})" : $semester->nama) : '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $mahasiswa->status_akademik->nama ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">
                                {{ $search ? 'Tidak ada hasil pencarian.' : 'Belum ada mahasiswa pada kategori biaya ini.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $mahasiswaList->links() }}
        </div>
    </div>

    {{-- Modal: Tambah Mahasiswa --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Tambah Mahasiswa ke Kategori Biaya</h3>
                    <button type="button" wire:click="closeModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4 p-6">
                    <p class="text-xs text-neutral-500">
                        Status penetapan akan dibuat <span class="font-semibold">aktif</span>; penetapan kategori biaya
                        aktif mahasiswa ini yang lama (jika ada) otomatis dinonaktifkan.
                    </p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mahasiswa *</label>

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
                                    placeholder="Cari NIM atau nama mahasiswa..."
                                    class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('selectedMahasiswaId') ring-2 ring-red-500 @enderror shadow-border"
                                />
                                @if ($mahasiswaSearch !== '')
                                    <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                        @forelse ($this->mahasiswaResults as $m)
                                            <button
                                                type="button"
                                                wire:click="selectMahasiswa({{ $m->id }}, '{{ addslashes(trim(($m->nim ?? '').' - '.$m->nama)) }}')"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-neutral-50"
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
                        @error('selectedMahasiswaId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Semester *</label>
                        <select
                            wire:model="selectedSemesterId"
                            class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('selectedSemesterId') ring-2 ring-red-500 @enderror shadow-border"
                        >
                            <option value="">— Pilih semester —</option>
                            @foreach ($this->semesterOptions as $semester)
                                <option value="{{ $semester->id }}">{{ $semester->kode ? "{$semester->nama} ({$semester->kode})" : $semester->nama }}</option>
                            @endforeach
                        </select>
                        @error('selectedSemesterId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 border-t border-neutral-200 pt-4">
                        <button type="button" wire:click="closeModal" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                            Batal
                        </button>
                        <button type="submit" class="flex-1 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
