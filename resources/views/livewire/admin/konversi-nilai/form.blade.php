@section('title', 'Tambah Konversi Nilai — ' . config('app.name'))
@section('header_title', 'Tambah Konversi Nilai')
@section('header_icon', 'repeat')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Konversi Nilai', 'route' => route('admin.akademik.konversi-nilai')],
        ['label' => 'Tambah'],
    ]])
@endsection

<div>
    @if ($submitError !== '')
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ $submitError }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-sm font-semibold text-neutral-700">Pilih Mahasiswa</h2>

            @if (! $selectedMahasiswaId)
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="mahasiswaSearch"
                        placeholder="Cari berdasarkan NIM atau nama..."
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                    />
                    @if ($mahasiswaSearch !== '')
                        <div class="mt-1 max-h-56 overflow-y-auto rounded-lg bg-white shadow-border-lg">
                            @forelse ($this->mahasiswaSearchResults as $mhs)
                                <button
                                    type="button"
                                    wire:click="selectMahasiswaOption({{ $mhs->id }})"
                                    class="block w-full px-3 py-2 text-left text-sm transition hover:bg-neutral-100"
                                >
                                    <span class="font-medium text-neutral-900">{{ $mhs->nim }}</span>
                                    <span class="text-neutral-600"> - {{ $mhs->nama }}</span>
                                </button>
                            @empty
                                <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
                @error('selectedMahasiswaId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            @else
                <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-neutral-700">Detail Mahasiswa</h3>
                        <button type="button" wire:click="clearSelectedMahasiswa" class="text-xs font-medium text-neutral-500 transition hover:text-neutral-900">
                            Ganti
                        </button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <span class="text-xs font-medium text-neutral-500">NIM</span>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->nim }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-neutral-500">Nama</span>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->nama }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-neutral-500">Prodi</span>
                            <p class="text-sm font-semibold text-neutral-900">
                                {{ $this->selectedMahasiswa->prodi->nama ?? '—' }}
                                {{ $this->selectedMahasiswa->prodi->kode ? '('.$this->selectedMahasiswa->prodi->kode.')' : '' }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if ($selectedMahasiswaId)
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kurikulum *</label>
                        <x-searchable-select
                            model="kurikulumId"
                            :options="$this->kurikulumOptions"
                            placeholder="— Pilih kurikulum —"
                            :live="true"
                        />
                        @if (empty($this->kurikulumOptions))
                            <p class="mt-1.5 text-xs text-neutral-500">Tidak ada kurikulum aktif untuk prodi mahasiswa ini.</p>
                        @endif
                        @error('kurikulumId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Konversi *</label>
                        <x-searchable-select
                            model="idJenisKonversi"
                            :options="$this->jenisKonversiOptions"
                            placeholder="— Pilih jenis —"
                        />
                        @error('idJenisKonversi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-neutral-700">Detail Mata Kuliah</h2>
                </div>

                @if (! $kurikulumId)
                    <p class="text-sm text-amber-700">Pilih kurikulum terlebih dahulu agar daftar mata kuliah baru tersedia.</p>
                @endif

                @foreach ($rows as $index => $row)
                    <div wire:key="konversi-row-{{ $index }}" class="rounded-2xl bg-white p-6 shadow-border">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-neutral-500">Baris {{ $index + 1 }}</span>
                            <button
                                type="button"
                                wire:click="removeRow({{ $index }})"
                                @if (count($rows) <= 1) disabled @endif
                                title="{{ count($rows) <= 1 ? 'Minimal satu baris' : 'Hapus baris' }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 shadow-border"
                            >
                                <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <fieldset class="space-y-2 rounded-lg p-3 shadow-border">
                                <legend class="px-1 text-xs font-semibold text-neutral-600">Mata Kuliah Lama</legend>
                                <input
                                    type="text"
                                    wire:model="rows.{{ $index }}.kode_mk_lama"
                                    placeholder="Kode MK lama"
                                    class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                />
                                @error('rows.'.$index.'.kode_mk_lama') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                <input
                                    type="text"
                                    wire:model="rows.{{ $index }}.nama_mk_lama"
                                    placeholder="Nama MK lama"
                                    class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                />
                                @error('rows.'.$index.'.nama_mk_lama') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                <div class="grid grid-cols-2 gap-2">
                                    <input
                                        type="number" min="1"
                                        wire:model="rows.{{ $index }}.sks_lama"
                                        placeholder="SKS"
                                        class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                    />
                                    <input
                                        type="text" maxlength="5"
                                        wire:model="rows.{{ $index }}.nilai_lama"
                                        placeholder="Nilai lama"
                                        class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                    />
                                </div>
                                @error('rows.'.$index.'.sks_lama') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                @error('rows.'.$index.'.nilai_lama') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </fieldset>
                            <fieldset class="space-y-2 rounded-lg p-3 shadow-border">
                                <legend class="px-1 text-xs font-semibold text-neutral-600">Mata Kuliah Baru (dari kurikulum)</legend>
                                {{-- wire:key disematkan ke kurikulumId supaya elemen ini di-unmount/mount
                                     ulang setiap kurikulum berganti — x-searchable-select memakai
                                     wire:ignore, jadi tanpa key yang berubah, opsi lama (dari kurikulum
                                     sebelumnya) akan tetap tersangkut walau kurikulumMatkulOptions
                                     sudah berubah di server. --}}
                                <div wire:key="konversi-row-{{ $index }}-mk-baru-{{ $kurikulumId ?? 'none' }}">
                                    <x-searchable-select
                                        :model="'rows.'.$index.'.id_kurikulum_matkul'"
                                        :options="$this->kurikulumMatkulOptions"
                                        :placeholder="$kurikulumId ? '— Pilih MK di kurikulum —' : 'Pilih kurikulum dulu'"
                                    />
                                </div>
                                @error('rows.'.$index.'.id_kurikulum_matkul') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-neutral-600">Nilai Baru</label>
                                    <input
                                        type="text" maxlength="5"
                                        wire:model="rows.{{ $index }}.nilai_baru"
                                        placeholder="Contoh: A, AB, B"
                                        class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                    />
                                    @error('rows.'.$index.'.nilai_baru') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </fieldset>
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-center">
                    <button
                        type="button"
                        wire:click="addRow"
                        @if (! $kurikulumId) disabled @endif
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40 shadow-border"
                    >
                        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                        Tambah Baris
                    </button>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.akademik.konversi-nilai') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            @if ($selectedMahasiswaId)
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                    <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                    Simpan
                </button>
            @endif
        </div>
    </form>
</div>
