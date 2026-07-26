@php
    $roleLabels = ['admin' => 'Admin / Operator', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'];
@endphp

@section('title', 'Pengguna — ' . config('app.name'))
@section('header_title', 'Pengguna')
@section('header_subtitle', 'Kelola akun pengguna panel dan portal')
@section('header_icon', 'users')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Pengguna'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.pengguna.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Pengguna
    </a>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama, email, atau nomor telepon..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Tipe Akun</label>
                    <select wire:model.live="filterRole" class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                        <option value="">Semua Tipe</option>
                        @foreach ($roleLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status</label>
                    <select wire:model.live="filterStatus" class="w-full rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak Aktif</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Telepon</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($penggunaList as $pengguna)
                        <tr wire:key="pengguna-{{ $pengguna->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $pengguna->name }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pengguna->email }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pengguna->username ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700">
                                    {{ $roleLabels[$pengguna->role] ?? $pengguna->role }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pengguna->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                                    {{ $pengguna->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pengguna->phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.pengguna.show', $pengguna->id) }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat Detail"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada data pengguna.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $penggunaList->links() }}
        </div>
    </div>
</div>
