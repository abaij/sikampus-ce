@extends('layouts.web')

@section('title', 'Test Upload — ' . config('app.name'))

@section('header_title', 'Test Upload')
@section('header_subtitle', 'Superadmin')
@section('header_icon', 'upload')

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
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-slate-900">Uji Upload File</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Gunakan halaman ini untuk menguji upload file ke backend. Format yang diizinkan adalah
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm text-slate-800">pdf</code>,
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm text-slate-800">jpg</code>,
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm text-slate-800">jpeg</code>, dan
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm text-slate-800">png</code> dengan ukuran maksimal
                <strong>5 MB</strong>. File yang berhasil diunggah akan disimpan ke
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm text-slate-800">storage/app/public/test-uploads</code>.
            </p>
        </div>

        @if (session('status'))
            <div
                class="mb-6 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                role="status"
            >
                <i data-lucide="circle-check" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div
                class="mb-6 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-900"
                role="alert"
            >
                <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <form method="post" action="{{ route('superadmin.test-upload.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label for="upload_file" class="mb-2 block text-sm font-medium text-slate-700">Pilih file</label>
                <input
                    id="upload_file"
                    name="upload_file"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none ring-indigo-500 transition file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 @error('upload_file') border-red-500 focus:border-red-500 focus:ring-red-500/20 @enderror"
                >
                <p class="mt-2 text-sm text-slate-500">Maksimal 5 MB per file.</p>
                @error('upload_file')
                    <p class="mt-2 flex items-center gap-1 text-sm text-red-600">
                        <i data-lucide="alert-circle" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <i data-lucide="upload-cloud" class="h-4 w-4" aria-hidden="true"></i>
                    Upload File
                </button>
            </div>
        </form>
    </div>
@endsection
