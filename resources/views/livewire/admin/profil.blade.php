@section('title', 'Profil Saya — ' . config('app.name'))
@section('header_title', 'Profil Saya')
@section('header_subtitle', 'Kelola informasi akun dan password Anda')
@section('header_icon', 'user')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Profil Saya'],
    ]])
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Profil</h3>
        <form wire:submit="saveProfil" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('name') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email *</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telepon</label>
                    <input type="text" wire:model="phone" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kota</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat</label>
                    <textarea wire:model="address" rows="3" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Provinsi</label>
                    <input type="text" wire:model="state" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                    <input type="text" wire:model="zip" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Negara</label>
                    <input type="text" wire:model="country" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
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
