@section('title', ($mahasiswaId ? 'Ubah' : 'Tambah') . ' Mahasiswa — ' . config('app.name'))
@section('header_title', ($mahasiswaId ? 'Ubah' : 'Tambah') . ' Mahasiswa')
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => array_filter([
        ['label' => 'Administrasi'],
        ['label' => 'Mahasiswa', 'route' => route('admin.administrasi.mahasiswa')],
        $mahasiswaId ? ['label' => $nama, 'route' => route('admin.administrasi.mahasiswa.show', $mahasiswaId)] : null,
        ['label' => $mahasiswaId ? 'Ubah' : 'Tambah'],
    ])])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Personal</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                    <input type="text" wire:model="nama" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIM</label>
                    <input type="text" wire:model="nim" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nim') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nim') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. WA</label>
                    <input type="text" wire:model="no_wa" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Handphone</label>
                    <input type="text" wire:model="handphone" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Kelamin</label>
                    <x-searchable-select
                        model="jenis_kelamin"
                        :options="['L' => 'Laki-laki', 'P' => 'Perempuan']"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tempat Lahir</label>
                    <input type="text" wire:model="id_tempat_lahir" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir</label>
                    <input type="date" wire:model="tanggal_lahir" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. KTP</label>
                    <input type="text" wire:model="no_ktp" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NPWP</label>
                    <input type="text" wire:model="npwp" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-sm font-semibold text-neutral-900">Alamat</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat</label>
                    <textarea wire:model="alamat" rows="2" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">RT</label>
                    <input type="text" wire:model="rt" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">RW</label>
                    <input type="text" wire:model="rw" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Dusun</label>
                    <input type="text" wire:model="dusun" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelurahan</label>
                    <input type="text" wire:model="kelurahan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                    <input type="text" wire:model="kode_pos" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kecamatan</label>
                    <input type="text" wire:model="id_kecamatan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Negara</label>
                    <x-searchable-select
                        model="id_negara"
                        :options="$negaraOptions"
                        placeholder="— Pilih negara —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Provinsi</label>
                    <x-searchable-select
                        model="id_provinsi"
                        :options="$provinsiOptions"
                        placeholder="— Pilih provinsi —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kota</label>
                    <x-searchable-select
                        model="id_kota"
                        :options="$kotaOptions"
                        placeholder="— Pilih kota —"
                    />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Akademik</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Program Studi</label>
                    <x-searchable-select
                        model="id_prodi"
                        :options="$prodiOptions->mapWithKeys(fn ($p) => [$p->id => $p->nama.($p->kode ? ' ('.$p->kode.')' : '')])->all()"
                        placeholder="— Pilih prodi —"
                    />
                    @error('id_prodi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelas Mahasiswa</label>
                    <x-searchable-select
                        model="id_kelompok_kelas"
                        :options="$kelompokKelasOptions"
                        placeholder="— Pilih kelas mahasiswa —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Semester Masuk</label>
                    <x-searchable-select
                        model="id_semester_masuk"
                        :options="$semesterOptions->mapWithKeys(fn ($s) => [$s->id => $s->nama.' ('.$s->kode.')'])->all()"
                        placeholder="— Pilih semester —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status Akademik</label>
                    <x-searchable-select
                        model="id_status_akademik"
                        :options="$statusAkademikOptions"
                        placeholder="— Pilih status akademik —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jalur Masuk</label>
                    <x-searchable-select
                        model="id_jalur_masuk"
                        :options="$jalurMasukOptions"
                        placeholder="— Pilih jalur masuk —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Daftar</label>
                    <x-searchable-select
                        model="id_jenis_daftar"
                        :options="$jenisDaftarOptions"
                        placeholder="— Pilih jenis daftar —"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mulai Semester</label>
                    <input type="text" wire:model="mulai_semester" placeholder="Contoh: 20241" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">SKS Diakui</label>
                    <input type="number" min="0" wire:model="sks_diakui" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Sekolah Asal</label>
                    <input type="text" wire:model="sekolah_asal" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIS</label>
                    <input type="text" wire:model="nis" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NISN</label>
                    <input type="text" wire:model="nisn" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-sm font-semibold text-neutral-900">Orang Tua & Wali</h2>

            <h3 class="mb-3 text-xs font-semibold uppercase text-neutral-500">Ayah</h3>
            <div class="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Ayah</label>
                    <input type="text" wire:model="ayah" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIK Ayah</label>
                    <input type="text" wire:model="nik_ayah" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir Ayah</label>
                    <input type="date" wire:model="tgl_lahir_ayah" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pendidikan Ayah</label>
                    <x-searchable-select model="id_pddk_ayah" :options="$pendidikanOptions" placeholder="— Pilih pendidikan —" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pekerjaan Ayah</label>
                    <x-searchable-select model="id_pekerjaan_ayah" :options="$pekerjaanOptions" placeholder="— Pilih pekerjaan —" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Penghasilan Ayah</label>
                    <x-searchable-select model="id_penghasilan_ayah" :options="$penghasilanOptions" placeholder="— Pilih penghasilan —" />
                </div>
            </div>

            <h3 class="mb-3 text-xs font-semibold uppercase text-neutral-500">Ibu</h3>
            <div class="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Ibu</label>
                    <input type="text" wire:model="ibu" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIK Ibu</label>
                    <input type="text" wire:model="nik_ibu" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir Ibu</label>
                    <input type="date" wire:model="tgl_lahir_ibu" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pendidikan Ibu</label>
                    <x-searchable-select model="id_pddk_ibu" :options="$pendidikanOptions" placeholder="— Pilih pendidikan —" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pekerjaan Ibu</label>
                    <x-searchable-select model="id_pekerjaan_ibu" :options="$pekerjaanOptions" placeholder="— Pilih pekerjaan —" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Penghasilan Ibu</label>
                    <x-searchable-select model="id_penghasilan_ibu" :options="$penghasilanOptions" placeholder="— Pilih penghasilan —" />
                </div>
            </div>

            <h3 class="mb-3 text-xs font-semibold uppercase text-neutral-500">Wali</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Wali</label>
                    <input type="text" wire:model="wali" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIK Wali</label>
                    <input type="text" wire:model="nik_wali" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir Wali</label>
                    <input type="date" wire:model="tgl_lahir_wali" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pendidikan Wali</label>
                    <x-searchable-select model="id_pddk_wali" :options="$pendidikanOptions" placeholder="— Pilih pendidikan —" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pekerjaan Wali</label>
                    <x-searchable-select model="id_pekerjaan_wali" :options="$pekerjaanOptions" placeholder="— Pilih pekerjaan —" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Penghasilan Wali</label>
                    <x-searchable-select model="id_penghasilan_wali" :options="$penghasilanOptions" placeholder="— Pilih penghasilan —" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Keuangan</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jumlah Biaya Masuk</label>
                    <input type="number" min="0" step="0.01" wire:model="jml_biaya_masuk" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Penerima KPS</label>
                    <input type="text" wire:model="penerima_kps" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. KPS</label>
                    <input type="text" wire:model="no_kps" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $backUrl }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
