@extends('layouts.web')

@section('title', 'Migrasi — ' . config('app.name'))

@section('header_title', 'Migrasi')
@section('header_subtitle', 'Superadmin')
@section('header_icon', 'database')

@section('header_actions')
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
            Keluar
        </button>
    </form>
@endsection

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h2 class="text-xl font-semibold text-slate-900">Migrasi</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Halaman ini dapat Anda isi dengan alat atau daftar migrasi. Sementara belum ada konten.
        </p>
    </div>
@endsection
