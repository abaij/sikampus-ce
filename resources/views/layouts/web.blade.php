<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'SIAK'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        </style>
    @endif

    <script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js" defer></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
@hasSection('header_title')
<div class="min-h-screen">
    <header class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-4 sm:px-6">
            <a
                href="{{ request()->routeIs('admin.*') ? route('admin.dashboard') : route('dashboard') }}"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white transition hover:bg-indigo-700"
                title="Dashboard"
            >
                <i data-lucide="layout-dashboard" class="h-5 w-5" aria-hidden="true"></i>
            </a>
            <nav class="flex flex-wrap items-center gap-1 sm:gap-2" aria-label="Utama">
                @hasSection('nav')
                    @yield('nav')
                @else
                    <a
                        href="{{ route('superadmin.konfigurasi') }}"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('superadmin.konfigurasi') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        Konfigurasi
                    </a>
                    <a
                        href="{{ route('superadmin.migrasi') }}"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('superadmin.migrasi') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        Migrasi
                    </a>
                    <a
                        href="{{ route('superadmin.test-upload') }}"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('superadmin.test-upload') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        Test Upload
                    </a>
                @endif
            </nav>
            <div class="flex shrink-0 items-center">
                {{-- Persistent di semua halaman (dulu diulang per-halaman lewat header_actions) —
                     route logout menyesuaikan area: panel admin (admin.*) vs superadmin. --}}
                <form method="post" action="{{ request()->routeIs('admin.*') ? route('admin.logout') : route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        @hasSection('breadcrumb')
            <div class="mb-4">
                @yield('breadcrumb')
            </div>
        @endif

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <i data-lucide="@yield('header_icon', 'layout-dashboard')" class="h-5 w-5" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold text-slate-900">@yield('header_title')</h1>
                    @hasSection('header_subtitle')
                    <p class="mt-1 text-sm text-slate-500">@yield('header_subtitle')</p>
                    @endif
                </div>
            </div>
            @hasSection('page_actions')
                <div class="flex shrink-0 items-center gap-2">
                    @yield('page_actions')
                </div>
            @endif
        </div>

        @yield('content')
    </main>
</div>
@else
@yield('content')
@endif
<script>
    function renderLucideIcons() {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }

    document.addEventListener('DOMContentLoaded', renderLucideIcons);

    // Livewire hanya mengonversi <i data-lucide="..."> jadi SVG saat page load penuh.
    // Baris baru yang muncul lewat update AJAX (mis. klik pagination, search reaktif)
    // tidak pernah ter-render jadi ikon sampai halaman di-refresh manual — perbaiki
    // dengan menjalankan ulang createIcons() setiap kali Livewire selesai morph DOM.
    document.addEventListener('livewire:init', function () {
        Livewire.hook('morph.updated', renderLucideIcons);
    });

    document.addEventListener('livewire:navigated', renderLucideIcons);
</script>
@stack('scripts')
</body>
</html>
