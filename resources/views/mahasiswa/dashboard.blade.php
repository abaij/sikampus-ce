@extends('layouts.mahasiswa')

@section('title', 'Dashboard Mahasiswa — ' . config('app.name'))
@section('header_title', 'Dashboard Mahasiswa')
@section('header_subtitle', 'Selamat datang, ' . (auth()->user()->name ?? 'Mahasiswa'))

@section('content')
    <div class="rounded-2xl bg-white p-10 text-center shadow-border">
        <h2 class="text-lg font-semibold text-neutral-900">Dashboard mahasiswa segera hadir</h2>
        <p class="mt-2 text-sm text-neutral-600">Modul ini sedang dalam pengembangan.</p>
    </div>
@endsection
