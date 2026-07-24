@section('title', ($dosenId ? 'Ubah' : 'Tambah') . ' Dosen — ' . config('app.name'))
@section('header_title', ($dosenId ? 'Ubah' : 'Tambah') . ' Dosen')
@section('header_icon', 'user-round')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Dosen', 'route' => route('admin.administrasi.dosen')],
        ['label' => $dosenId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Data Utama</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Lengkap *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('nama') border-red-500 @enderror" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Gelar Depan</label>
                    <input type="text" wire:model="gelar_depan" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Gelar Belakang</label>
                    <input type="text" wire:model="gelar_belakang" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kode Dosen</label>
                    <input type="text" wire:model="kode_dosen" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('kode_dosen') border-red-500 @enderror" />
                    @error('kode_dosen') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('email') border-red-500 @enderror" />
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">NIP</label>
                    <input type="text" wire:model="nip" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('nip') border-red-500 @enderror" />
                    @error('nip') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">NIDN</label>
                    <input type="text" wire:model="nidn" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('nidn') border-red-500 @enderror" />
                    @error('nidn') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">NIDK</label>
                    <input type="text" wire:model="nidk" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">NUPN</label>
                    <input type="text" wire:model="nupn" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">No. HP</label>
                    <input type="text" wire:model="no_hp" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Data Pribadi</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Tempat Lahir</label>
                    <input type="text" wire:model="tempat_lahir" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal Lahir</label>
                    <input type="date" wire:model="tanggal_lahir" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Jenis Kelamin</label>
                    <x-searchable-select
                        model="jenis_kelamin"
                        :options="['L' => 'Laki-laki', 'P' => 'Perempuan']"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Agama</label>
                    <x-searchable-select
                        model="agama"
                        :options="$agamaOptions"
                        placeholder="— Pilih agama —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Status Perkawinan</label>
                    <input type="text" wire:model="status_perkawinan" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kewarganegaraan</label>
                    <input type="text" wire:model="kewarganegaraan" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Alamat</label>
                    <textarea wire:model="alamat" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></textarea>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Negara</label>
                    <x-searchable-select
                        model="id_negara"
                        :options="$negaraOptions"
                        placeholder="— Pilih negara —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Provinsi</label>
                    <x-searchable-select
                        model="id_provinsi"
                        :options="$provinsiOptions"
                        placeholder="— Pilih provinsi —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kota</label>
                    <x-searchable-select
                        model="id_kota"
                        :options="$kotaOptions"
                        placeholder="— Pilih kota —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kode Pos</label>
                    <input type="text" wire:model="kode_pos" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Kuota Bimbingan</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kuota Bimbingan Akademik</label>
                    <input type="number" min="0" wire:model="kuota_bimbingan_akademik" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kuota Bimbingan Tugas Akhir</label>
                    <input type="number" min="0" wire:model="kuota_bimbingan_ta" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.administrasi.dosen') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
