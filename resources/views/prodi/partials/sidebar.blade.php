@php
    // Struktur & urutan menu meniru ProdiSidebar.tsx di siak-frontend
    // (../siak-frontend/components/prodi/ProdiSidebar.tsx). Rute yang modulnya belum diport
    // tetap didaftarkan lewat 'prodi.coming-soon' supaya sidebar sudah lengkap sejak awal —
    // cukup ganti target rute saat modulnya dibangun.
    $prodiNavItems = [
        ['route' => 'prodi.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
        ['route' => 'prodi.kurikulum', 'label' => 'Kurikulum', 'icon' => 'file-text'],
        ['label' => 'Perkuliahan', 'icon' => 'calendar', 'children' => [
            ['route' => 'prodi.jadwal-kuliah', 'label' => 'Jadwal Kuliah'],
            ['route' => 'prodi.krs', 'label' => 'KRS'],
            ['route' => 'prodi.konversi-nilai', 'label' => 'Konversi Nilai'],
        ]],
        ['route' => 'prodi.matkul', 'label' => 'Mata Kuliah', 'icon' => 'layers'],
        ['route' => 'prodi.mahasiswa', 'label' => 'Mahasiswa', 'icon' => 'users'],
        ['route' => 'prodi.dosen', 'label' => 'Dosen', 'icon' => 'graduation-cap'],
    ];

    $isChildActive = fn (array $child) => request()->routeIs($child['route'].'*');
    $isItemActive = function (array $item) use ($isChildActive) {
        if (isset($item['children'])) {
            return collect($item['children'])->contains($isChildActive);
        }

        return request()->routeIs($item['route'].'*');
    };
@endphp

@foreach ($prodiNavItems as $item)
    @if (isset($item['children']))
        @php $itemActive = $isItemActive($item); @endphp
        <details class="group" @if ($itemActive) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemActive ? 'bg-sky-50 text-sky-700' : 'text-neutral-700 hover:bg-neutral-100' }}">
                <span class="flex items-center gap-3">
                    <i data-lucide="{{ $item['icon'] }}" class="h-5 w-5 {{ $itemActive ? 'text-sky-600' : 'text-neutral-500' }}" aria-hidden="true"></i>
                    {{ $item['label'] }}
                </span>
                <i data-lucide="chevron-right" class="h-4 w-4 text-neutral-400 transition group-open:rotate-90" aria-hidden="true"></i>
            </summary>
            <div class="mt-1 ml-4 space-y-1 border-l-2 border-neutral-200 pl-4">
                @foreach ($item['children'] as $child)
                    <a
                        href="{{ route($child['route']) }}"
                        class="block rounded-lg px-3 py-2 text-sm transition {{ $isChildActive($child) ? 'bg-sky-50 font-medium text-sky-700' : 'text-neutral-600 hover:bg-neutral-100' }}"
                    >
                        {{ $child['label'] }}
                    </a>
                @endforeach
            </div>
        </details>
    @else
        @php $itemActive = $isItemActive($item); @endphp
        <a
            href="{{ route($item['route']) }}"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemActive ? 'bg-sky-50 text-sky-700' : 'text-neutral-700 hover:bg-neutral-100' }}"
        >
            <i data-lucide="{{ $item['icon'] }}" class="h-5 w-5 {{ $itemActive ? 'text-sky-600' : 'text-neutral-500' }}" aria-hidden="true"></i>
            {{ $item['label'] }}
        </a>
    @endif
@endforeach
