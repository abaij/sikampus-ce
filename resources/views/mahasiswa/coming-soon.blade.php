@extends('layouts.mahasiswa')

@section('title', $title . ' — ' . config('app.name'))
@section('header_title', $title)
@section('header_subtitle', 'Modul ini sedang dalam pengembangan.')

@section('content')
    <div class="rounded-2xl bg-white p-10 text-center shadow-border">
        <h2 class="text-lg font-semibold text-neutral-900">{{ $title }} segera hadir</h2>
        <p class="mt-2 text-sm text-neutral-600">Modul ini sedang dalam pengembangan.</p>
    </div>
@endsection
