@section('title', 'Reset Password — ' . config('app.name'))

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
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-neutral-900">Reset Password</h1>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-border">
            @if (! $hasRequiredParams)
                <div class="flex flex-col items-center py-4 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-600">
                        <i data-lucide="x-circle" class="h-9 w-9" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-900">Link tidak valid</h2>
                    <p class="mt-2 text-sm text-neutral-600">Link reset password tidak valid atau sudah tidak berlaku.</p>
                    <a
                        href="{{ route('forgot-password') }}"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                    >
                        Minta Link Baru
                    </a>
                </div>
            @elseif ($successMessage)
                <div class="flex flex-col items-center py-4 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="check-circle-2" class="h-9 w-9" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-900">Password berhasil diperbarui</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ $successMessage }}</p>
                    <a
                        href="{{ route('login') }}"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                    >
                        <i data-lucide="log-in" class="h-4 w-4" aria-hidden="true"></i>
                        Masuk Sekarang
                    </a>
                </div>
            @else
                @if ($errorMessage)
                    <div class="mb-6 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                <form wire:submit="resetPassword" class="space-y-5">
                    <div>
                        <label for="password" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-neutral-700">
                            <i data-lucide="lock" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                            Password Baru
                        </label>
                        <input
                            id="password"
                            type="password"
                            wire:model="password"
                            required
                            minlength="8"
                            autofocus
                            class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('password') ring-2 ring-red-500 @enderror"
                            placeholder="Minimal 8 karakter"
                        />
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="passwordConfirmation" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-neutral-700">
                            <i data-lucide="lock" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                            Konfirmasi Password Baru
                        </label>
                        <input
                            id="passwordConfirmation"
                            type="password"
                            wire:model="passwordConfirmation"
                            required
                            minlength="8"
                            class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('passwordConfirmation') ring-2 ring-red-500 @enderror"
                            placeholder="Ulangi password baru"
                        />
                        @error('passwordConfirmation')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="resetPassword"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="resetPassword">Perbarui Password</span>
                        <span wire:loading wire:target="resetPassword" class="flex items-center gap-2">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                            Memproses...
                        </span>
                    </button>
                </form>
            @endif
        </div>

        @if ($hasRequiredParams && ! $successMessage)
            <p class="mt-6 text-center text-sm text-neutral-500">
                <a href="{{ route('login') }}" class="font-semibold text-neutral-900 hover:underline">Kembali ke Login</a>
            </p>
        @endif
    </div>
</div>
