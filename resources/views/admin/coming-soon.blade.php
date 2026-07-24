@extends('layouts.web')

@section('title', $title . ' — ' . config('app.name'))
@section('header_title', $title)
@section('header_subtitle', 'Modul akademik')
@section('header_icon', 'hammer')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => $title],
    ]])
@endsection

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">{{ $title }} segera hadir</h2>
        <p class="mt-2 text-sm text-slate-600">Modul ini sedang dalam pengembangan.</p>
    </div>
@endsection
