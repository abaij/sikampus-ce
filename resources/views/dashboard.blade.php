@extends('layouts.web')

@section('title', 'Dashboard — ' . config('app.name'))

@section('header_title', 'Dashboard')
@section('header_subtitle', 'Superadmin')

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
        <div class="mb-6 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <i data-lucide="user-check" class="h-6 w-6" aria-hidden="true"></i>
            </div>
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-neutral-900">Selamat datang</h2>
                <p class="mt-1 text-neutral-600">
                    Anda masuk sebagai <strong class="text-neutral-800">{{ Auth::user()->name }}</strong>
                    <span class="text-neutral-500">({{ Auth::user()->email }})</span>.
                </p>
            </div>
        </div>
        <p class="text-sm leading-relaxed text-neutral-600">
            Ini adalah halaman dashboard sesi web untuk superadmin. Anda dapat mengembangkan halaman ini mengarah ke modul administrasi atau tautan ke aplikasi frontend.
        </p>
    </div>

    {{-- Widget yang di-push plugin lewat DashboardWidgetRegistry (lihat AppServiceProvider) --}}
    @foreach (($pluginDashboardWidgets ?? []) as $pluginWidgetHtml)
        {!! $pluginWidgetHtml !!}
    @endforeach
@endsection
