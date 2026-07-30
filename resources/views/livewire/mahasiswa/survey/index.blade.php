@section('title', 'Survey — ' . config('app.name'))
@section('header_title', 'Survey')
@section('header_subtitle', 'Pilih survey dan mata kuliah yang ingin Anda isi.')

@php $surveys = $this->surveys; @endphp

<div class="space-y-4">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (empty($surveys))
        <div class="rounded-2xl bg-white p-8 text-center shadow-border">
            <i data-lucide="clipboard-list" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada survey aktif</p>
            <p class="mt-1 text-sm text-neutral-500">Saat ini tidak ada survey yang dapat diisi.</p>
        </div>
    @else
        @foreach ($surveys as $survey)
            @php
                $isExpanded = $expandedSurveyId === $survey['id'];
                $total = count($survey['mata_kuliah']);
                $sudahDiisi = collect($survey['mata_kuliah'])->where('sudah_diisi', true)->count();
            @endphp
            <div wire:key="survey-{{ $survey['id'] }}" class="overflow-hidden rounded-2xl bg-white shadow-border">
                <button type="button" wire:click="toggle({{ $survey['id'] }})" class="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition hover:bg-neutral-50">
                    <div class="flex flex-1 items-start gap-4">
                        <div class="mt-1 rounded-lg bg-sky-100 p-2">
                            <i data-lucide="clipboard-list" class="h-5 w-5 text-sky-600" aria-hidden="true"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-1 font-semibold text-neutral-900">{{ $survey['nama'] }}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-neutral-500">
                                @if ($survey['semester'])
                                    <span>{{ $survey['semester']->nama }}</span>
                                @endif
                                @if ($survey['tanggal_mulai'] && $survey['tanggal_selesai'])
                                    <span>{{ \Carbon\Carbon::parse($survey['tanggal_mulai'])->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($survey['tanggal_selesai'])->translatedFormat('d F Y') }}</span>
                                @endif
                                <span class="flex items-center gap-1">
                                    <i data-lucide="book-open" class="h-4 w-4" aria-hidden="true"></i>
                                    {{ $sudahDiisi }}/{{ $total }} mata kuliah
                                </span>
                            </div>
                            @if ($survey['keterangan'])
                                <p class="mt-2 text-sm text-neutral-600">{{ $survey['keterangan'] }}</p>
                            @endif
                        </div>
                    </div>
                    <i data-lucide="{{ $isExpanded ? 'chevron-up' : 'chevron-down' }}" class="h-5 w-5 shrink-0 text-neutral-400" aria-hidden="true"></i>
                </button>

                @if ($isExpanded)
                    <div class="border-t border-neutral-100 px-6 py-4">
                        @if (empty($survey['mata_kuliah']))
                            <p class="py-4 text-center text-sm text-neutral-500">Tidak ada mata kuliah yang dikontrak untuk survey ini.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($survey['mata_kuliah'] as $mk)
                                    <div class="flex items-center justify-between rounded-xl bg-neutral-50 p-4 shadow-border">
                                        <div class="flex flex-1 items-start gap-3">
                                            <i data-lucide="{{ $mk['sudah_diisi'] ? 'check-circle-2' : 'circle' }}" class="mt-1 h-5 w-5 shrink-0 {{ $mk['sudah_diisi'] ? 'text-emerald-600' : 'text-neutral-400' }}" aria-hidden="true"></i>
                                            <div class="flex-1">
                                                <div class="mb-1 flex items-center gap-2">
                                                    <span class="font-mono text-sm font-semibold text-sky-600">{{ $mk['kode_matkul'] }}</span>
                                                    @if ($mk['sudah_diisi'])
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                            <i data-lucide="check-circle-2" class="h-3 w-3" aria-hidden="true"></i>
                                                            Sudah diisi
                                                        </span>
                                                    @endif
                                                </div>
                                                <h4 class="mb-1 font-medium text-neutral-900">{{ $mk['nama_matkul'] }}</h4>
                                                <div class="flex flex-wrap items-center gap-2 text-xs text-neutral-500">
                                                    <span>Kelas: {{ $mk['nama_kelas'] }}</span>
                                                    <span>&bull;</span>
                                                    <span>{{ $mk['sks'] }} SKS</span>
                                                    @if ($mk['prodi'])
                                                        <span>&bull;</span>
                                                        <span>{{ $mk['prodi']->nama }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <a
                                                href="{{ route('mahasiswa.survey.isi', ['id' => $survey['id'], 'krs' => $mk['id_krs']]) }}"
                                                class="rounded-lg px-4 py-2 text-sm font-medium transition {{ $mk['sudah_diisi'] ? 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200' : 'bg-sky-600 text-white hover:bg-sky-700' }}"
                                            >
                                                {{ $mk['sudah_diisi'] ? 'Lihat/Ubah' : 'Isi Survey' }}
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
