@section('title', 'Jadwal Mengajar — ' . config('app.name'))
@section('header_title', 'Jadwal Mengajar')
@section('header_subtitle', 'Ringkasan semua slot jadwal mengajar Anda. Untuk daftar per mata kuliah, buka Kelas Mata Kuliah.')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <x-searchable-select
                model="filterSemester"
                :options="$this->semesterOptions"
                :live="true"
                placeholder="Semua semester"
            />
        </div>
    </div>

    @php
        $viewMonthDate = \Carbon\CarbonImmutable::parse($viewMonth);
        $events = $this->eventsByDate;
        $today = \Carbon\CarbonImmutable::now()->format('Y-m-d');
    @endphp

    <div class="rounded-2xl bg-white p-4 shadow-border sm:p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold text-neutral-900 capitalize">{{ $viewMonthDate->translatedFormat('F Y') }}</h3>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="thisMonth"
                    class="rounded-lg bg-neutral-50 px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-100"
                >
                    Bulan ini
                </button>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="prevMonth"
                        class="rounded-lg p-2 text-neutral-600 shadow-border hover:bg-neutral-50"
                        aria-label="Bulan sebelumnya"
                    >
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                    <button
                        type="button"
                        wire:click="nextMonth"
                        class="rounded-lg p-2 text-neutral-600 shadow-border hover:bg-neutral-50"
                        aria-label="Bulan berikutnya"
                    >
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl bg-neutral-200 ring-1 ring-neutral-200">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h)
                <div class="bg-neutral-100 px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-600 sm:text-xs">
                    {{ $h }}
                </div>
            @endforeach

            @foreach ($this->monthGrid as $idx => $date)
                @if (! $date)
                    <div wire:key="empty-{{ $idx }}" class="min-h-[72px] bg-neutral-50/90 sm:min-h-[100px]"></div>
                @else
                    @php
                        $key = $date->format('Y-m-d');
                        $dayEvents = $events[$key] ?? [];
                        $isToday = $key === $today;
                        $maxTampil = 3;
                    @endphp
                    <div wire:key="day-{{ $key }}" class="flex min-h-[72px] flex-col border-t border-neutral-100 bg-white p-1 sm:min-h-[100px] {{ $isToday ? 'ring-inset ring-2 ring-sky-300/80' : '' }}">
                        <div class="mb-0.5 text-right text-[11px] font-semibold tabular-nums sm:text-xs {{ $isToday ? 'text-sky-700' : 'text-neutral-700' }}">
                            {{ $date->day }}
                        </div>
                        <div class="flex flex-1 flex-col gap-0.5 overflow-hidden">
                            @foreach (array_slice($dayEvents, 0, $maxTampil) as $item)
                                @php
                                    $jam = $item['jam_mulai'] ? substr($item['jam_mulai'], 0, 5) : '—';
                                    $href = $item['id_kelas'] && $item['id_jadwal']
                                        ? route('dosen.jadwal.detail', ['kelasId' => $item['id_kelas'], 'jadwalId' => $item['id_jadwal'], 'id_semester' => $filterSemester !== '' ? $filterSemester : null])
                                        : null;
                                @endphp
                                @if ($href)
                                    <a
                                        href="{{ $href }}"
                                        title="{{ $item['nama_matkul'] }} · {{ $jam }}"
                                        class="truncate rounded bg-sky-50 px-0.5 py-0.5 text-[9px] font-medium text-sky-900 ring-1 ring-sky-100 transition hover:bg-sky-100 hover:ring-sky-200 sm:text-[10px]"
                                    >
                                        <span class="tabular-nums">{{ $jam }}</span> {{ $item['nama_matkul'] }}
                                    </a>
                                @else
                                    <span class="truncate rounded bg-neutral-100 px-0.5 py-0.5 text-[9px] text-neutral-500 sm:text-[10px]" title="{{ $item['nama_matkul'] }}">
                                        {{ $jam }} {{ $item['nama_matkul'] }}
                                    </span>
                                @endif
                            @endforeach
                            @if (count($dayEvents) > $maxTampil)
                                <span class="text-[9px] text-neutral-500 sm:text-[10px]">+{{ count($dayEvents) - $maxTampil }} lagi</span>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
