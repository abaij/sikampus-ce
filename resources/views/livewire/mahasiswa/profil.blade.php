@section('title', 'Profil Saya — ' . config('app.name'))
@section('header_title', 'Profil Saya')
@section('header_subtitle', 'Kelola informasi akun dan password Anda')
@section('header_icon', 'user')

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Foto Profil</h3>
        <form wire:submit="saveFoto" class="space-y-5">
            <div class="flex items-center gap-6">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-100">
                    @if ($foto_upload && $foto_upload->isPreviewable())
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
                    <p class="mt-1.5 text-xs text-neutral-500">JPG atau PNG, maksimal 5MB.</p>
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

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Profil</h3>
        <form wire:submit="saveProfil" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIM</label>
                    <input type="text" value="{{ $nim ?: '—' }}" disabled class="w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500 shadow-border" />
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
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. Handphone</label>
                    <input type="text" wire:model="handphone" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. WhatsApp</label>
                    <input type="text" wire:model="no_wa" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
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

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Ganti Password</h3>
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
</div>
