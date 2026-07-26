@extends('layouts.web')

@section('title', 'Dashboard Admin — ' . config('app.name'))

@section('header_title', 'Panel Admin')
@section('header_subtitle', 'Data master & akademik')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('content')
    <div class="rounded-2xl bg-white p-8 shadow-border">
        <div class="mb-6 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-900">
                <i data-lucide="layout-dashboard" class="h-6 w-6" aria-hidden="true"></i>
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
            Gunakan menu di atas untuk mengelola data master akademik (Fakultas, Prodi, Jenjang, Jalur Masuk).
        </p>
    </div>
@endsection
