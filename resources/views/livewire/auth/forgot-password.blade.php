@section('title', 'Lupa Password — ' . config('app.name'))

<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-8 flex flex-col items-center text-center">
            @if ($logoPerguruanTinggiSrc)
                <img
                    src="{{ $logoPerguruanTinggiSrc }}"
                    alt="{{ $namaPerguruanTinggi !== '' ? $namaPerguruanTinggi : config('app.name') }}"
                    class="mb-4 h-14 w-14 rounded-2xl bg-white object-contain shadow-border"
                />
            @else
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-neutral-900 text-white shadow-lg shadow-neutral-900/10">
                    <i data-lucide="key-round" class="h-8 w-8" aria-hidden="true"></i>
                </div>
            @endif
            <p class="text-sm font-medium text-neutral-500">{{ $namaPerguruanTinggi !== '' ? $namaPerguruanTinggi : config('app.name') }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-neutral-900">Lupa Password</h1>
            <p class="mt-2 text-sm text-neutral-600">Masukkan email akun Anda, kami akan kirimkan link reset password.</p>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-border">
            @if ($errorMessage)
                <div class="mb-6 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                    <span>{{ $errorMessage }}</span>
                </div>
            @endif

            @if ($successMessage)
                <div class="mb-6 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                    <span>{{ $successMessage }}</span>
                </div>
            @endif

            <form wire:submit="sendResetLink" class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-neutral-700">
                        <i data-lucide="mail" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        wire:model="email"
                        required
                        autofocus
                        class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror"
                        placeholder="nama@institusi.ac.id"
                    />
                    @error('email')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendResetLink"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="sendResetLink">Kirim Link Reset</span>
                    <span wire:loading wire:target="sendResetLink" class="flex items-center gap-2">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                        Mengirim...
                    </span>
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-neutral-500">
            Ingat password Anda?
            <a href="{{ route('login') }}" class="font-semibold text-neutral-900 hover:underline">Kembali ke Login</a>
        </p>
    </div>
</div>
