@extends('layouts.web')

@section('title', 'Plugin — ' . config('app.name'))

@section('header_title', 'Plugin')
@section('header_subtitle', 'Superadmin')
@section('header_icon', 'puzzle')

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
    <div class="space-y-6">
        @if (session('status'))
            <div
                class="flex gap-3 whitespace-pre-line rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                role="status"
            >
                <i data-lucide="circle-check" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div
                class="flex gap-3 whitespace-pre-line rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-900"
                role="alert"
            >
                <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="rounded-2xl bg-white p-8 shadow-border">
            <div class="mb-6">
                <h2 class="text-xl font-semibold tracking-tight text-neutral-900">Instal Plugin Baru</h2>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Unggah berkas <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-sm text-neutral-800">.zip</code> plugin
                    berisi <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-sm text-neutral-800">plugin.json</code>.
                    Plugin tidak otomatis aktif setelah diinstal — aktifkan dari daftar di bawah setelah Anda
                    memastikan sumbernya terpercaya. Menginstal plugin setara dengan menjalankan kode PHP
                    baru di aplikasi ini.
                </p>
            </div>

            <form method="post" action="{{ route('superadmin.plugins.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label for="plugin_zip" class="mb-2 block text-sm font-medium text-neutral-700">Berkas ZIP plugin</label>
                    <input
                        id="plugin_zip"
                        name="plugin_zip"
                        type="file"
                        accept=".zip"
                        class="block w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-900 shadow-border outline-none ring-neutral-900 transition file:mr-4 file:rounded-md file:border-0 file:bg-neutral-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-neutral-800 hover:file:bg-neutral-200 focus:border-neutral-900 focus:bg-white focus:ring-2 focus:ring-neutral-900/10 @error('plugin_zip') ring-2 ring-red-500 @enderror"
                    >
                    @error('plugin_zip')
                        <p class="mt-2 flex items-center gap-1 text-sm text-red-600">
                            <i data-lucide="alert-circle" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2"
                    >
                        <i data-lucide="upload-cloud" class="h-4 w-4" aria-hidden="true"></i>
                        Instal Plugin
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-border">
            <h2 class="mb-6 text-xl font-semibold tracking-tight text-neutral-900">Plugin Terinstal</h2>

            @if ($plugins->isEmpty())
                <p class="text-sm text-neutral-500">Belum ada plugin yang diinstal.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                <th class="py-2 pr-4">Nama</th>
                                <th class="py-2 pr-4">Versi</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Migrasi terakhir</th>
                                <th class="py-2 pr-0 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($plugins as $plugin)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <div class="font-medium text-neutral-900">{{ $plugin->name }}</div>
                                        <div class="text-xs text-neutral-500">{{ $plugin->slug }}</div>
                                    </td>
                                    <td class="py-3 pr-4 text-neutral-600">{{ $plugin->version }}</td>
                                    <td class="py-3 pr-4">
                                        @if ($plugin->enabled)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-neutral-600">
                                        {{ $plugin->last_migrated_at?->translatedFormat('d M Y H:i') ?? '—' }}
                                    </td>
                                    <td class="py-3 pr-0">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @if ($plugin->migrations_relative_path)
                                                <form method="post" action="{{ route('superadmin.plugins.migrate', $plugin) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-600 shadow-border transition hover:bg-neutral-50"
                                                    >
                                                        <i data-lucide="database" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                                        Migrasi
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($plugin->enabled)
                                                <form method="post" action="{{ route('superadmin.plugins.disable', $plugin) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-600 shadow-border transition hover:bg-neutral-50"
                                                    >
                                                        Nonaktifkan
                                                    </button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('superadmin.plugins.enable', $plugin) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-neutral-800"
                                                    >
                                                        Aktifkan
                                                    </button>
                                                </form>
                                            @endif

                                            <details class="relative">
                                                <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 shadow-border transition hover:bg-red-50">
                                                    Hapus
                                                </summary>
                                                <form
                                                    method="post"
                                                    action="{{ route('superadmin.plugins.destroy', $plugin) }}"
                                                    class="absolute right-0 z-10 mt-2 w-64 space-y-2 rounded-lg border border-neutral-100 bg-white p-3 shadow-lg"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <p class="text-xs text-neutral-600">
                                                        Ketik <strong>{{ $plugin->slug }}</strong> untuk konfirmasi. Migration yang
                                                        sudah dijalankan tidak akan di-rollback otomatis.
                                                    </p>
                                                    <input
                                                        type="text"
                                                        name="confirm_slug"
                                                        placeholder="{{ $plugin->slug }}"
                                                        class="block w-full rounded-md border-0 bg-neutral-50 px-2.5 py-1.5 text-xs text-neutral-900 shadow-border outline-none focus:ring-2 focus:ring-red-500"
                                                    >
                                                    <button
                                                        type="submit"
                                                        class="w-full rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700"
                                                    >
                                                        Hapus Permanen
                                                    </button>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
