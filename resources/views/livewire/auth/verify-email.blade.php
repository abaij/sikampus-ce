@section('title', 'Verifikasi Email — ' . config('app.name'))

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
                    <i data-lucide="mail-check" class="h-8 w-8" aria-hidden="true"></i>
                </div>
            @endif
            <p class="text-sm font-medium text-neutral-500">{{ $namaPerguruanTinggi !== '' ? $namaPerguruanTinggi : config('app.name') }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-neutral-900">Verifikasi Email</h1>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-border">
            <div class="flex flex-col items-center py-4 text-center">
                @if ($status === 'success')
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="check-circle-2" class="h-9 w-9" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-900">Email berhasil diverifikasi</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ $message }}</p>
                    <a
                        href="{{ route('login') }}"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                    >
                        <i data-lucide="log-in" class="h-4 w-4" aria-hidden="true"></i>
                        Masuk Sekarang
                    </a>
                @else
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-600">
                        <i data-lucide="x-circle" class="h-9 w-9" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-900">Verifikasi gagal</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ $message }}</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        <a
                            href="{{ route('aktivasi') }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                        >
                            Coba Lagi
                        </a>
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                        >
                            Kembali ke Login
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
