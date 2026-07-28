@extends('layouts.web')

@php $group = $group ?? 'Akademik'; @endphp

@section('title', $title . ' — ' . config('app.name'))
@section('header_title', $title)
@section('header_subtitle', 'Modul ' . strtolower($group))
@section('header_icon', 'hammer')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => $group],
        ['label' => $title],
    ]])
@endsection

@section('content')
    <div class="rounded-2xl bg-white p-10 text-center shadow-border">
        <h2 class="text-lg font-semibold text-neutral-900">{{ $title }} segera hadir</h2>
        <p class="mt-2 text-sm text-neutral-600">Modul ini sedang dalam pengembangan.</p>
    </div>
@endsection
