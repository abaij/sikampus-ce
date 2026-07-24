@extends('layouts.web')

@section('title', 'Akses Ditolak — ' . config('app.name'))

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
            <i data-lucide="shield-alert" class="h-6 w-6" aria-hidden="true"></i>
        </div>
        <h1 class="text-lg font-semibold text-slate-900">Akses Ditolak</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            {{ $exception->getMessage() ?: 'Anda tidak memiliki hak akses untuk membuka halaman ini.' }}
        </p>
        @auth
            <a
                href="{{ route('admin.dashboard') }}"
                class="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
            >
                <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
                Kembali ke Dashboard
            </a>
        @endauth
    </div>
@endsection
