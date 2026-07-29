@section('title', 'Akun Saya — ' . config('app.name'))
@section('header_title', 'Akun Saya')
@section('header_subtitle', 'Kelola biodata, password, dan foto profil Anda')

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Tab murni lewat properti Livewire ($activeTab), bukan Alpine — supaya state tab tetap
         benar setelah validasi gagal (redirect balik ke server-rendered state), sama seperti
         pola tab lain di panel ini. --}}
    <div class="flex gap-1 border-b border-neutral-200">
        <button
            type="button"
            wire:click="$set('activeTab', 'biodata')"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $activeTab === 'biodata' ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-700' }}"
        >
            Biodata
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'password')"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $activeTab === 'password' ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-700' }}"
        >
            Password
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'foto')"
            class="border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $activeTab === 'foto' ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-700' }}"
        >
            Foto
        </button>
    </div>

    @if ($activeTab === 'biodata')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <form wire:submit="saveProfil" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Dosen</label>
                        <input type="text" value="{{ $kode_dosen ?: '—' }}" disabled class="w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIP / NIDN</label>
                        <input type="text" value="{{ ($nip ?: '—') . ' / ' . ($nidn ?: '—') }}" disabled class="w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                        <input type="text" wire:model="nama" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Gelar Depan</label>
                        <input type="text" wire:model="gelar_depan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Gelar Belakang</label>
                        <input type="text" wire:model="gelar_belakang" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tempat Lahir</label>
                        <input type="text" wire:model="tempat_lahir" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir</label>
                        <input type="date" wire:model="tanggal_lahir" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_lahir') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('tanggal_lahir') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Kelamin</label>
                        <x-searchable-select
                            model="jenis_kelamin"
                            :options="['L' => 'Laki-laki', 'P' => 'Perempuan']"
                            placeholder="— Pilih jenis kelamin —"
                        />
                        @error('jenis_kelamin') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Agama</label>
                        <x-searchable-select
                            model="agama"
                            :options="$agamaOptions"
                            placeholder="— Pilih agama —"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status Perkawinan</label>
                        <input type="text" wire:model="status_perkawinan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kewarganegaraan</label>
                        <input type="text" wire:model="kewarganegaraan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. HP</label>
                        <input type="text" wire:model="no_hp" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
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
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                        <input type="text" wire:model="kode_pos" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat</label>
                        <textarea wire:model="alamat" rows="3" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                    </div>
                </div>
                <div class="flex justify-end border-t border-neutral-200 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                        <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($activeTab === 'password')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <form wire:submit="savePassword" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Password Saat Ini *</label>
                        <input type="password" wire:model="current_password" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('current_password') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('current_password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Password Baru *</label>
                        <input type="password" wire:model="new_password" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('new_password') ring-2 ring-red-500 @enderror shadow-border" placeholder="Minimal 8 karakter" />
                        @error('new_password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Konfirmasi Password Baru *</label>
                        <input type="password" wire:model="new_password_confirmation" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('new_password_confirmation') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('new_password_confirmation') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end border-t border-neutral-200 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700">
                        <i data-lucide="key-round" class="h-4 w-4" aria-hidden="true"></i>
                        Ubah Password
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($activeTab === 'foto')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <form wire:submit="saveFoto" class="space-y-5">
                <div class="flex items-center gap-6">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-100">
                        @if ($foto_upload)
                            <img src="{{ $foto_upload->temporaryUrl() }}" alt="" class="h-full w-full object-cover" />
                        @elseif ($foto)
                            <img src="{{ asset('storage/'.$foto) }}" alt="" class="h-full w-full object-cover" />
                        @else
                            <i data-lucide="user" class="h-10 w-10 text-neutral-400" aria-hidden="true"></i>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Unggah Foto Baru</label>
                        <input type="file" wire:model="foto_upload" accept="image/png,image/jpeg" class="block w-full text-sm text-neutral-600 file:mr-4 file:rounded-lg file:border-0 file:bg-neutral-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-neutral-800" />
                        <p class="mt-1.5 text-xs text-neutral-500">JPG atau PNG, maksimal 2MB.</p>
                        @error('foto_upload') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="foto_upload" class="mt-1.5 text-xs text-neutral-500">Mengunggah…</div>
                    </div>
                </div>
                <div class="flex justify-end border-t border-neutral-200 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800" @if (! $foto_upload) disabled @endif>
                        <i data-lucide="upload" class="h-4 w-4" aria-hidden="true"></i>
                        Simpan Foto
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
