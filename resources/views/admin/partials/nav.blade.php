@php
    $adminNavGroups = [
        [
            'label' => 'Akademik',
            'items' => [
                ['label' => 'Kurikulum', 'children' => [
                    ['route' => 'admin.akademik.kurikulum', 'label' => 'Kurikulum'],
                    ['route' => 'admin.akademik.matkul', 'label' => 'Mata Kuliah'],
                ]],
                ['label' => 'Perkuliahan', 'children' => [
                    ['route' => 'admin.akademik.kelas', 'label' => 'Kelas'],
                    ['route' => 'admin.akademik.jadwal', 'label' => 'Jadwal Kuliah'],
                    ['route' => 'admin.akademik.perkuliahan', 'label' => 'Perkuliahan'],
                ]],
                ['label' => 'Nilai', 'children' => [
                    ['route' => 'admin.akademik.nilai', 'label' => 'Nilai'],
                    ['route' => 'admin.akademik.konversi-nilai', 'label' => 'Konversi Nilai'],
                    ['route' => 'admin.akademik.jenis-penilaian', 'label' => 'Jenis Penilaian'],
                    ['route' => 'admin.akademik.rentang-nilai', 'label' => 'Rentang Nilai'],
                ]],
                ['route' => 'admin.akademik.krs', 'label' => 'KRS'],
                ['label' => 'Akhir Studi', 'children' => [
                    ['route' => 'admin.akademik.tugas-akhir', 'label' => 'Tugas Akhir'],
                    ['route' => 'admin.akademik.yudisium', 'label' => 'Yudisium'],
                    ['route' => 'admin.akademik.wisuda', 'label' => 'Wisuda'],
                ]],
            ],
        ],
        [
            'label' => 'Administrasi',
            'items' => [
                ['label' => 'Dosen', 'children' => [
                    ['route' => 'admin.administrasi.dosen', 'label' => 'Dosen'],
                    ['route' => 'admin.administrasi.dosen-wali', 'label' => 'Dosen Wali'],
                ]],
                ['label' => 'Mahasiswa', 'children' => [
                    ['route' => 'admin.administrasi.mahasiswa', 'label' => 'Mahasiswa'],
                    ['route' => 'admin.administrasi.kelas-mahasiswa', 'label' => 'Kelas Mahasiswa'],
                ]],
                ['label' => 'Institusi', 'children' => [
                    ['route' => 'admin.fakultas.index', 'label' => 'Fakultas'],
                    ['route' => 'admin.prodi.index', 'label' => 'Prodi'],
                    ['route' => 'admin.perguruan-tinggi', 'label' => 'Perguruan Tinggi'],
                ]],
                ['route' => 'admin.jenjang.index', 'label' => 'Jenjang'],
                ['route' => 'admin.administrasi.ruangan', 'label' => 'Ruangan'],
            ],
        ],
        [
            'label' => 'Pengaturan',
            'items' => [
                ['label' => 'Akademik', 'children' => [
                    ['route' => 'admin.semester.index', 'label' => 'Semester'],
                    ['route' => 'admin.jalur-masuk.index', 'label' => 'Jalur Masuk'],
                    ['route' => 'admin.jenis-daftar.index', 'label' => 'Jenis Daftar'],
                    ['route' => 'admin.status-akademik.index', 'label' => 'Status Akademik'],
                ]],
                ['label' => 'Pengguna', 'children' => [
                    ['route' => 'admin.pengguna.index', 'label' => 'Pengguna'],
                    ['route' => 'admin.pengguna.role.index', 'label' => 'Role'],
                    ['route' => 'admin.pengguna.permission.index', 'label' => 'Permission'],
                ]],
            ],
        ],
    ];

    // Item aktif kalau route-nya sendiri cocok, atau (untuk item yang punya submenu) salah satu child-nya cocok.
    $isItemActive = function (array $item) {
        if (isset($item['children'])) {
            return collect($item['children'])->contains(fn ($child) => request()->routeIs($child['route'].'*'));
        }

        return request()->routeIs($item['route'].'*');
    };
@endphp

<a
    href="{{ route('admin.dashboard') }}"
    class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-neutral-100 text-neutral-800' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}"
>
    Dashboard
</a>

{{-- Dropdown grup pakai CSS hover (bukan Alpine/JS) karena layout ini dipakai baik oleh
     halaman Livewire maupun Blade biasa (mis. dashboard), dan Livewire hanya menyuntikkan
     Alpine ke halaman yang benar-benar memuat komponen Livewire.
     Nested submenu (mis. Administrasi > Mahasiswa > Mahasiswa/Kelas Mahasiswa) pakai Tailwind
     named group (group/l1, group/l2) supaya hover level-2 tidak ikut memicu/ketiban level-1. --}}
@foreach ($adminNavGroups as $group)
    @php
        $groupActive = collect($group['items'])->contains($isItemActive);
    @endphp
    <div class="group/l1 relative">
        <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition {{ $groupActive ? 'bg-neutral-100 text-neutral-800' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}"
        >
            {{ $group['label'] }}
            <i data-lucide="chevron-down" class="h-3.5 w-3.5" aria-hidden="true"></i>
        </button>
        <div class="invisible absolute left-0 top-full z-20 w-52 pt-1 opacity-0 transition-opacity duration-100 group-hover/l1:visible group-hover/l1:opacity-100">
            <div class="rounded-lg bg-white py-1 shadow-border-lg">
                @foreach ($group['items'] as $item)
                    @if (isset($item['children']))
                        @php $itemActive = $isItemActive($item); @endphp
                        <div class="group/l2 relative">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition {{ $itemActive ? 'bg-neutral-100 text-neutral-800' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}"
                            >
                                {{ $item['label'] }}
                                <i data-lucide="chevron-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
                            </button>
                            <div class="invisible absolute left-full top-0 z-30 w-52 pl-1 opacity-0 transition-opacity duration-100 group-hover/l2:visible group-hover/l2:opacity-100">
                                <div class="rounded-lg bg-white py-1 shadow-border-lg">
                                    @foreach ($item['children'] as $child)
                                        <a
                                            href="{{ route($child['route']) }}"
                                            class="block px-3 py-2 text-sm transition {{ request()->routeIs($child['route'].'*') ? 'bg-neutral-100 text-neutral-800' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}"
                                        >
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ route($item['route']) }}"
                            class="block px-3 py-2 text-sm transition {{ request()->routeIs($item['route'].'*') ? 'bg-neutral-100 text-neutral-800' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endforeach
