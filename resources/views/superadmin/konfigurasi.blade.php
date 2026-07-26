@extends('layouts.web')

@section('title', 'Konfigurasi — ' . config('app.name'))

@section('header_title', 'Konfigurasi')
@section('header_subtitle', 'Superadmin')
@section('header_icon', 'settings')

@section('header_actions')
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2"
        >
            <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
            Keluar
        </button>
    </form>
@endsection

@section('content')
    <div class="rounded-2xl bg-white p-8 shadow-border">
        <div class="mb-6">
            <h2 class="text-xl font-semibold tracking-tight text-neutral-900">File .env</h2>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                Edit isi <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-sm text-neutral-800">.env</code> di root aplikasi. Simpan hanya jika Anda memahami dampaknya terhadap lingkungan produksi.
            </p>
        </div>

        @if (session('status'))
            <div
                class="mb-6 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                role="status"
            >
                <i data-lucide="circle-check" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div
                class="mb-6 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-900"
                role="alert"
            >
                <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (! $envWritable)
            <div
                class="mb-6 flex gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                role="alert"
            >
                <i data-lucide="triangle-alert" class="h-5 w-5 shrink-0 text-amber-600" aria-hidden="true"></i>
                <span>
                    @if ($envExists)
                        File .env ada tetapi tidak dapat ditulis oleh proses web server. Ubah izin berkas atau pemiliknya agar penyimpanan dari halaman ini berhasil.
                    @else
                        Direktori aplikasi tidak dapat ditulis; pembuatan .env baru dari halaman ini tidak akan berhasil.
                    @endif
                </span>
            </div>
        @endif

        @if (! $envExists)
            <div
                class="mb-6 flex gap-3 rounded-lg bg-neutral-50 px-4 py-3 text-sm text-neutral-700 shadow-border"
                role="status"
            >
                <i data-lucide="file-question" class="h-5 w-5 shrink-0 text-neutral-500" aria-hidden="true"></i>
                <span>File .env belum ada. Menyimpan formulir akan membuat berkas baru.</span>
            </div>
        @endif

        <form method="post" action="{{ route('superadmin.konfigurasi.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="env_content" class="mb-2 block text-sm font-medium text-neutral-700">Isi .env</label>
                <textarea
                    id="env_content"
                    name="env_content"
                    rows="24"
                    spellcheck="false"
                    class="block w-full rounded-lg bg-neutral-50 px-3 py-2.5 font-mono text-sm leading-relaxed text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:bg-white focus:ring-2 focus:ring-neutral-900/10 @error('env_content') ring-2 ring-red-500 @enderror"
                    @if (! $envWritable) disabled @endif
                >{{ old('env_content', $envContent) }}</textarea>
                @error('env_content')
                    <p class="mt-2 flex items-center gap-1 text-sm text-red-600">
                        <i data-lucide="alert-circle" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-3">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @if (! $envWritable) disabled @endif
                >
                    <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                    Simpan .env
                </button>
            </div>
        </form>
    </div>
@endsection
