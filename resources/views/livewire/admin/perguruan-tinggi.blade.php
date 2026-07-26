@section('title', 'Perguruan Tinggi — ' . config('app.name'))
@section('header_title', 'Perguruan Tinggi')
@section('header_subtitle', 'Data identitas perguruan tinggi')
@section('header_icon', 'landmark')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Institusi'],
        ['label' => 'Perguruan Tinggi'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama Perguruan Tinggi *</label>
                    <input type="text" wire:model="nama" placeholder="Universitas Bersama" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nama') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat</label>
                    <textarea wire:model="alamat" rows="3" placeholder="Jl. Contoh No. 123" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Yayasan</label>
                    <input type="text" wire:model="yayasan" placeholder="Yayasan Pendidikan Tinggi" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Provinsi</label>
                    <x-searchable-select
                        model="id_provinsi"
                        :options="$provinsiOptions"
                        placeholder="— Pilih provinsi —"
                        :live="true"
                    />
                    @error('id_provinsi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kota</label>
                    <x-searchable-select
                        model="id_kota"
                        :options="$kotaOptions"
                        placeholder="{{ $id_provinsi ? '— Pilih kota/kabupaten —' : '— Pilih provinsi terlebih dahulu —' }}"
                    />
                    @error('id_kota') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                    <input type="text" wire:model="kodePos" placeholder="12345" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telepon</label>
                    <input type="text" wire:model="telepon" placeholder="+62 21 12345678" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email</label>
                    <input type="email" wire:model="email" placeholder="info@univ.ac.id" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Website</label>
                    <input type="text" wire:model="website" placeholder="https://www.univ.ac.id" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <label class="mb-3 block text-sm font-medium text-neutral-700">Logo Perguruan Tinggi</label>

            @if ($logo)
                <div class="relative inline-block">
                    <div class="relative flex h-32 w-48 items-center justify-center overflow-hidden rounded-xl border-2 border-neutral-200 bg-white">
                        <img src="{{ $logo }}" alt="Logo Perguruan Tinggi" class="h-full w-full object-contain" />
                    </div>
                    <button
                        type="button"
                        wire:click="removeLogo"
                        class="absolute -right-2 -top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-white shadow-lg transition hover:bg-rose-600"
                        title="Hapus logo"
                    >
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>
            @else
                <label class="flex h-32 w-48 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-neutral-300 text-center transition hover:border-neutral-900 hover:bg-neutral-50">
                    <div wire:loading wire:target="logoUpload" class="flex flex-col items-center gap-2">
                        <i data-lucide="loader-circle" class="h-6 w-6 animate-spin text-neutral-400" aria-hidden="true"></i>
                        <span class="text-xs text-neutral-600">Mengupload...</span>
                    </div>
                    <div wire:loading.remove wire:target="logoUpload" class="flex flex-col items-center gap-2">
                        <i data-lucide="upload" class="h-6 w-6 text-neutral-400" aria-hidden="true"></i>
                        <span class="text-xs text-neutral-600">Klik untuk upload</span>
                        <span class="text-xs text-neutral-400">Max 2MB (JPG, PNG, GIF, WebP)</span>
                    </div>
                    <input type="file" wire:model="logoUpload" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="hidden" />
                </label>
            @endif
            @error('logoUpload') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
