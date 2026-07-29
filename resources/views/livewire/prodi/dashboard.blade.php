@section('title', 'Dashboard Prodi — ' . config('app.name'))
@section('header_title', 'Dashboard Prodi')
@section('header_subtitle', 'Selamat datang, ' . (auth()->user()->name ?? 'Admin Prodi'))

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="flex items-center gap-4">
                <div class="rounded-xl bg-indigo-100 p-3">
                    <i data-lucide="graduation-cap" class="h-6 w-6 text-indigo-600" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Prodi</p>
                    <p class="text-xl font-semibold text-neutral-900">—</p>
                    <p class="text-xs text-neutral-400">Fungsi khusus prodi</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="flex items-center gap-4">
                <div class="rounded-xl bg-emerald-100 p-3">
                    <i data-lucide="users" class="h-6 w-6 text-emerald-600" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Mahasiswa</p>
                    <p class="text-xl font-semibold text-neutral-900">—</p>
                    <p class="text-xs text-neutral-400">Per prodi</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="flex items-center gap-4">
                <div class="rounded-xl bg-amber-100 p-3">
                    <i data-lucide="book-open" class="h-6 w-6 text-amber-600" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Kelas</p>
                    <p class="text-xl font-semibold text-neutral-900">—</p>
                    <p class="text-xs text-neutral-400">Per prodi</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="flex items-center gap-4">
                <div class="rounded-xl bg-rose-100 p-3">
                    <i data-lucide="clipboard-list" class="h-6 w-6 text-rose-600" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">KRS / Nilai</p>
                    <p class="text-xl font-semibold text-neutral-900">—</p>
                    <p class="text-xs text-neutral-400">Fungsi nantinya</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="text-base font-semibold text-neutral-900">Dashboard Admin Prodi</h2>
        <p class="mt-2 text-sm text-neutral-600">
            Anda masuk sebagai <strong>Admin Prodi</strong> (dikenali dari status Kepala Prodi/Sekretaris
            Prodi). Gunakan menu di samping untuk mengelola kurikulum, mata kuliah, mahasiswa, dosen,
            jadwal kuliah, KRS, dan konversi nilai dalam lingkup program studi Anda.
        </p>
    </div>
</div>
