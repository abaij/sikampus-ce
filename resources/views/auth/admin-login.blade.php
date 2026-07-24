@extends('layouts.web')

@section('title', 'Login Panel Admin — ' . config('app.name'))

@section('content')
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-8 flex flex-col items-center text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/25">
                <i data-lucide="layout-dashboard" class="h-8 w-8" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Panel Admin</h1>
            <p class="mt-2 text-sm text-slate-600">Masuk dengan akun superadmin, akademik, atau keuangan.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            @if (session('error'))
                <div class="mb-6 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <form method="post" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-slate-700">
                        <i data-lucide="mail" class="h-4 w-4 text-slate-500" aria-hidden="true"></i>
                        Email
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        autofocus
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none ring-indigo-500 transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                        placeholder="nama@institusi.ac.id"
                    />
                    @error('email')
                        <p class="mt-1.5 flex items-center gap-1 text-sm text-red-600">
                            <i data-lucide="alert-circle" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-slate-700">
                        <i data-lucide="lock" class="h-4 w-4 text-slate-500" aria-hidden="true"></i>
                        Kata sandi
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none ring-indigo-500 transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 @error('password') border-red-500 @enderror"
                        placeholder="••••••••"
                    />
                    @error('password')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20" {{ old('remember') ? 'checked' : '' }} />
                        Ingat saya
                    </label>
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <i data-lucide="log-in" class="h-4 w-4" aria-hidden="true"></i>
                    Masuk
                </button>
            </form>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </p>
    </div>
</div>
@endsection
