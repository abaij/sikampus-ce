@section('title', ($prodiId ? 'Ubah' : 'Tambah') . ' Prodi — ' . config('app.name'))
@section('header_title', ($prodiId ? 'Ubah' : 'Tambah') . ' Prodi')
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Prodi', 'route' => route('admin.prodi.index')],
        ['label' => $prodiId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Prodi *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('nama') border-red-500 @enderror" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama (EN)</label>
                    <input type="text" wire:model="nama_en" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kode</label>
                    <input type="text" wire:model="kode" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('kode') border-red-500 @enderror" />
                    @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                    <x-searchable-select
                        model="status"
                        :clearable="false"
                        :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Fakultas</label>
                    <x-searchable-select
                        model="id_fakultas"
                        :options="$fakultasOptions"
                        placeholder="— Pilih fakultas —"
                    />
                    @error('id_fakultas') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Jenjang</label>
                    <x-searchable-select
                        model="id_jenjang"
                        :options="$jenjangOptions"
                        placeholder="— Pilih jenjang —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kepala Prodi</label>
                    <x-searchable-select
                        model="id_kaprodi"
                        :options="$dosenOptions"
                        placeholder="— Pilih dosen —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Sekretaris Prodi</label>
                    <x-searchable-select
                        model="id_sekprodi"
                        :options="$dosenOptions"
                        placeholder="— Pilih dosen —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Semester Aktif</label>
                    <x-searchable-select
                        model="id_semester_aktif"
                        :options="$semesterOptions"
                        placeholder="— Pilih semester —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">SKS Minimal</label>
                    <input type="number" wire:model="sks_minimal" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">IPK Lulus Minimal</label>
                    <input type="number" step="0.01" wire:model="ipk_lulus_minimal" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('ipk_lulus_minimal') border-red-500 @enderror" />
                    @error('ipk_lulus_minimal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Gelar</label>
                    <input type="text" wire:model="gelar" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Gelar Singkat</label>
                    <input type="text" wire:model="gelar_singkat" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Maks. Dosen Pembimbing</label>
                    <input type="number" wire:model="maks_dosen_pembimbing" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Maks. Dosen Penguji</label>
                    <input type="number" wire:model="maks_dosen_penguji" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div class="flex items-center gap-2 sm:col-span-2">
                    <input type="checkbox" wire:model="is_pmb_open" id="is_pmb_open" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" />
                    <label for="is_pmb_open" class="text-sm font-medium text-slate-700">Dibuka untuk PMB</label>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Deskripsi</label>
                    <textarea wire:model="deskripsi" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.prodi.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
