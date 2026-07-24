@extends('layouts.web')

@section('title', 'Dashboard Admin — ' . config('app.name'))

@section('header_title', 'Panel Admin')
@section('header_subtitle', 'Data master & akademik')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <i data-lucide="layout-dashboard" class="h-6 w-6" aria-hidden="true"></i>
            </div>
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Selamat datang</h2>
                <p class="mt-1 text-slate-600">
                    Anda masuk sebagai <strong class="text-slate-800">{{ Auth::user()->name }}</strong>
                    <span class="text-slate-500">({{ Auth::user()->email }})</span>.
                </p>
            </div>
        </div>
        <p class="text-sm leading-relaxed text-slate-600">
            Gunakan menu di atas untuk mengelola data master akademik (Fakultas, Prodi, Jenjang, Jalur Masuk).
        </p>
    </div>
@endsection
