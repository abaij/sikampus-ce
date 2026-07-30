@section('title', 'Aktivasi Akun — ' . config('app.name'))

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
                    <i data-lucide="user-check" class="h-8 w-8" aria-hidden="true"></i>
                </div>
            @endif
            <p class="text-sm font-medium text-neutral-500">{{ $namaPerguruanTinggi !== '' ? $namaPerguruanTinggi : config('app.name') }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-neutral-900">Aktivasi Akun</h1>
            <p class="mt-2 text-sm text-neutral-600">Untuk dosen dan mahasiswa yang belum pernah membuat akun.</p>
        </div>

        {{-- Stepper --}}
        <div class="mb-6 flex items-center justify-center gap-3">
            @foreach ([1 => 'Identitas', 2 => 'Akun', 3 => 'Selesai'] as $num => $label)
                <div class="flex items-center gap-3">
                    <div class="flex flex-col items-center gap-1">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold transition {{ $step >= $num ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-400' }}">
                            @if ($step > $num)
                                <i data-lucide="check" class="h-4 w-4" aria-hidden="true"></i>
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span class="text-[11px] font-medium {{ $step >= $num ? 'text-neutral-900' : 'text-neutral-400' }}">{{ $label }}</span>
                    </div>
                    @if ($num < 3)
                        <div class="h-0.5 w-10 rounded-full {{ $step > $num ? 'bg-neutral-900' : 'bg-neutral-200' }}"></div>
                    @endif
                </div>
            @endforeach
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

            {{-- Step 1: identitas --}}
            @if ($step === 1)
                <form wire:submit="checkIdentifier" class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Saya adalah</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                wire:click="$set('role', 'mahasiswa')"
                                class="flex flex-col items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium transition {{ $role === 'mahasiswa' ? 'bg-neutral-900 text-white' : 'text-neutral-600 shadow-border hover:bg-neutral-50' }}"
                            >
                                <i data-lucide="graduation-cap" class="h-5 w-5" aria-hidden="true"></i>
                                Mahasiswa
                            </button>
                            <button
                                type="button"
                                wire:click="$set('role', 'dosen')"
                                class="flex flex-col items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium transition {{ $role === 'dosen' ? 'bg-neutral-900 text-white' : 'text-neutral-600 shadow-border hover:bg-neutral-50' }}"
                            >
                                <i data-lucide="user-round" class="h-5 w-5" aria-hidden="true"></i>
                                Dosen
                            </button>
                        </div>
                        @error('role')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="identifier" class="mb-1.5 block text-sm font-medium text-neutral-700">
                            {{ $role === 'dosen' ? 'Kode Dosen' : 'NIM' }}
                        </label>
                        <input
                            id="identifier"
                            type="text"
                            wire:model="identifier"
                            required
                            class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('identifier') ring-2 ring-red-500 @enderror"
                            placeholder="{{ $role === 'dosen' ? 'Masukkan kode dosen' : 'Masukkan NIM' }}"
                        />
                        @error('identifier')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($hasAccount && $identifierData)
                        <div class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            <p class="font-medium">Akun untuk {{ $identifierData['nama'] }} sudah terdaftar.</p>
                            @if ($emailVerified)
                                <p class="mt-1">Email sudah terverifikasi. Silakan masuk menggunakan akun Anda.</p>
                                <a href="{{ route('login') }}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">
                                    <i data-lucide="log-in" class="h-4 w-4" aria-hidden="true"></i>
                                    Masuk
                                </a>
                            @else
                                <p class="mt-1">Email belum diverifikasi. Kirim ulang email verifikasi atau masuk jika sudah pernah verifikasi.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        wire:click="resendVerification"
                                        wire:loading.attr="disabled"
                                        wire:target="resendVerification"
                                        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-60"
                                    >
                                        <i data-lucide="mail" class="h-4 w-4" aria-hidden="true"></i>
                                        Kirim Ulang Email Verifikasi
                                    </button>
                                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
                                        Masuk
                                    </a>
                                </div>
                            @endif
                        </div>
                    @else
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="checkIdentifier"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="checkIdentifier" class="flex items-center gap-2">
                                Lanjutkan
                                <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                            </span>
                            <span wire:loading wire:target="checkIdentifier" class="flex items-center gap-2">
                                <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                                Memeriksa...
                            </span>
                        </button>
                    @endif
                </form>
            @endif

            {{-- Step 2: set email & password --}}
            @if ($step === 2 && $identifierData)
                <div class="mb-5 rounded-lg bg-neutral-50 px-4 py-3 text-sm">
                    <p class="font-medium text-neutral-900">{{ $identifierData['nama'] }}</p>
                    <p class="text-neutral-500">{{ $identifierData['nim'] ?? $identifierData['kode_dosen'] ?? '' }}</p>
                </div>

                <form wire:submit="register" class="space-y-5">
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
                            class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror"
                            placeholder="nama@email.com"
                        />
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-neutral-700">
                            <i data-lucide="lock" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                            Kata Sandi
                        </label>
                        <input
                            id="password"
                            type="password"
                            wire:model="password"
                            required
                            minlength="8"
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
                            Konfirmasi Kata Sandi
                        </label>
                        <input
                            id="passwordConfirmation"
                            type="password"
                            wire:model="passwordConfirmation"
                            required
                            minlength="8"
                            class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('passwordConfirmation') ring-2 ring-red-500 @enderror"
                            placeholder="Ulangi kata sandi"
                        />
                        @error('passwordConfirmation')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            wire:click="back"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                        >
                            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
                            Kembali
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="register"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="register">Buat Akun</span>
                            <span wire:loading wire:target="register" class="flex items-center gap-2">
                                <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                                Memproses...
                            </span>
                        </button>
                    </div>
                </form>
            @endif

            {{-- Step 3: sukses --}}
            @if ($step === 3)
                <div class="flex flex-col items-center py-4 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="check-circle-2" class="h-9 w-9" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-neutral-900">Akun berhasil dibuat</h2>
                    <p class="mt-2 text-sm text-neutral-600">
                        Silakan cek email <span class="font-medium text-neutral-900">{{ $email }}</span> untuk memverifikasi akun Anda sebelum masuk.
                    </p>
                    <a
                        href="{{ route('login') }}"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                    >
                        Kembali ke Login
                    </a>
                </div>
            @endif
        </div>

        @if ($step !== 3)
            <p class="mt-6 text-center text-sm text-neutral-500">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-neutral-900 hover:underline">Masuk di sini</a>
            </p>
        @endif
    </div>
</div>
