@section('title', 'License Key — ' . config('app.name'))
@section('header_title', 'License Key')
@section('header_subtitle', 'Kunci lisensi aplikasi, disimpan langsung ke file .env')
@section('header_icon', 'key-round')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Sistem'],
        ['label' => 'License Key'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($formError)
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0 text-rose-500" aria-hidden="true"></i>
            <span>{{ $formError }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <label class="mb-1.5 block text-sm font-medium text-neutral-700">License Key</label>
            <input
                type="text"
                wire:model="licenseKey"
                placeholder="XXXX-XXXX-XXXX-XXXX"
                autocomplete="off"
                class="w-full rounded-lg px-3 py-2.5 font-mono text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('licenseKey') ring-2 ring-red-500 @enderror shadow-border"
            />
            @error('licenseKey') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-neutral-500">
                Untuk mendapatkan license key, kunjungi
                <a
                    href="https://app.sikampus.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-sky-600 underline decoration-sky-300 underline-offset-2 transition hover:text-sky-700"
                >
                    akun Sikampus Anda
                </a>
            </p>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
