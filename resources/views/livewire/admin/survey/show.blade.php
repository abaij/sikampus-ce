@php
    $survey = $this->survey;
@endphp

@section('title', $survey->nama . ' — ' . config('app.name'))
@section('header_title', 'Detail Survey')
@section('header_subtitle', $survey->nama)
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Survey', 'route' => route('admin.administrasi.survey')],
        ['label' => $survey->nama],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
    @if (\App\Support\PanelAccess::can(auth()->user(), 'survey', 'update'))
        <a
            href="{{ route('admin.administrasi.survey.edit', $survey->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
    @endif
@endsection

{{-- Tombol aksi (modal, hapus, dst) sengaja berada di dalam badan komponen, bukan di section
     page_actions: layouts.web me-render page_actions di luar root <div> komponen, sehingga
     wire:click di sana tidak pernah terikat Livewire. --}}
<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-neutral-200">
        <nav class="-mb-px flex flex-wrap gap-6">
            @foreach ([['key' => 'detail', 'label' => 'Detail Survey'], ['key' => 'pertanyaan', 'label' => 'Pertanyaan Survey'], ['key' => 'statistik', 'label' => 'Statistik Pengisian']] as $tab)
                <button
                    type="button"
                    wire:click="setTab('{{ $tab['key'] }}')"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition {{ $activeTab === $tab['key'] ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab: Detail --}}
    @if ($activeTab === 'detail')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Survey</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Nama</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900">{{ $survey->nama }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Kode</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900">{{ $survey->kode }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Semester</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900">{{ $survey->semester->nama ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Status</p>
                    <p class="mt-1">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $survey->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-700' }}">
                            {{ $survey->is_active ? 'Aktif' : 'Tidak Aktif' }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Tanggal Mulai</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900">{{ $survey->tanggal_mulai?->translatedFormat('d F Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">Tanggal Selesai</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900">{{ $survey->tanggal_selesai?->translatedFormat('d F Y') ?? '—' }}</p>
                </div>
                @if ($survey->keterangan)
                    <div class="sm:col-span-2">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Keterangan</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $survey->keterangan }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Tab: Pertanyaan --}}
    @if ($activeTab === 'pertanyaan')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-neutral-900">Pertanyaan Survey</h3>
                @if (\App\Support\PanelAccess::can(auth()->user(), 'survey', 'update'))
                    <button
                        type="button"
                        wire:click="openAddQuestion"
                        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                    >
                        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                        Tambah Pertanyaan
                    </button>
                @endif
            </div>

            @php $questions = $this->questions; @endphp

            @if ($questions->isEmpty())
                <div class="py-12 text-center text-neutral-500">Belum ada pertanyaan survey.</div>
            @else
                <div class="space-y-3">
                    @foreach ($questions as $question)
                        <div class="overflow-hidden rounded-xl shadow-border" wire:key="question-{{ $question->id }}">
                            <button
                                type="button"
                                wire:click="toggleQuestion({{ $question->id }})"
                                class="flex w-full items-center justify-between p-4 text-left transition hover:bg-neutral-50"
                            >
                                <div>
                                    <p class="font-medium text-neutral-900">{{ $question->pertanyaan }}</p>
                                    <p class="mt-1 text-xs text-neutral-500">Tipe: {{ $question->tipe ?? 'essay' }} &middot; {{ $question->options->count() }} opsi</p>
                                </div>
                                <i data-lucide="{{ in_array($question->id, $expandedQuestions, true) ? 'chevron-up' : 'chevron-down' }}" class="h-4 w-4 shrink-0 text-neutral-400" aria-hidden="true"></i>
                            </button>

                            @if (in_array($question->id, $expandedQuestions, true))
                                <div class="border-t border-neutral-200 bg-neutral-50 p-4">
                                    <div class="mb-3 flex justify-end gap-2">
                                        @if (\App\Support\PanelAccess::can(auth()->user(), 'survey', 'update'))
                                            <button type="button" wire:click="openEditQuestion({{ $question->id }})" class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
                                                <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                                Ubah
                                            </button>
                                        @endif
                                        @if (\App\Support\PanelAccess::can(auth()->user(), 'survey', 'delete'))
                                            <button type="button" wire:click="confirmDeleteQuestion({{ $question->id }})" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                                <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                                Hapus
                                            </button>
                                        @endif
                                    </div>

                                    @if ($question->options->isNotEmpty())
                                        <p class="mb-2 text-xs font-semibold text-neutral-700">Opsi Jawaban:</p>
                                        <div class="space-y-2">
                                            @foreach ($question->options as $option)
                                                <div class="flex items-center gap-2 rounded-lg bg-white p-2 shadow-border">
                                                    <span class="w-6 text-xs font-medium text-neutral-500">{{ $loop->iteration }}.</span>
                                                    <span class="flex-1 text-sm text-neutral-900">{{ $option->opsi }}</span>
                                                    @if ($option->nilai_numerik !== null)
                                                        <span class="rounded bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700">Nilai: {{ $option->nilai_numerik }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-neutral-500">Tidak ada opsi jawaban (essay)</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Tab: Statistik --}}
    @if ($activeTab === 'statistik')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-neutral-900">Statistik Pengisian Survey</h3>
                <a
                    href="{{ route('admin.administrasi.survey.statistik.export', $survey->id) }}?{{ http_build_query(array_filter(['id_prodi' => $filterProdi !== '' ? $filterProdi : null, 'sort_by' => $sortBy, 'sort_order' => $sortOrder])) }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                >
                    <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
                    Export Excel
                </a>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-neutral-700">Filter Prodi</label>
                    <x-searchable-select
                        model="filterProdi"
                        :live="true"
                        :options="$this->prodiOptions->mapWithKeys(fn ($p) => [$p->id => $p->kode ? $p->nama.' ('.$p->kode.')' : $p->nama])->all()"
                        placeholder="Semua prodi"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-neutral-700">Urutkan Berdasarkan</label>
                    <x-searchable-select
                        model="sortBy"
                        :live="true"
                        :clearable="false"
                        :options="['nilai' => 'Nilai', 'pertanyaan' => 'Pertanyaan']"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-neutral-700">Urutan</label>
                    <x-searchable-select
                        model="sortOrder"
                        :live="true"
                        :clearable="false"
                        :options="['desc' => 'Tinggi ke Rendah', 'asc' => 'Rendah ke Tinggi']"
                    />
                </div>
            </div>

            @php $statistik = $this->statistik; @endphp

            <div class="mb-6 grid grid-cols-1 gap-4 rounded-xl bg-neutral-50 p-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-neutral-500">Total Responden</p>
                    <p class="text-2xl font-bold text-neutral-900">{{ $statistik['total_responden'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Mahasiswa yang sudah mengisi survey</p>
                </div>
                <div>
                    <p class="text-xs text-neutral-500">Total Pertanyaan</p>
                    <p class="text-2xl font-bold text-neutral-900">{{ count($statistik['pertanyaan']) }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Pertanyaan dalam survey</p>
                </div>
            </div>

            @if (empty($statistik['pertanyaan']))
                <div class="py-12 text-center text-neutral-500">Belum ada pertanyaan dalam survey ini.</div>
            @else
                <div class="space-y-4">
                    @foreach ($statistik['pertanyaan'] as $item)
                        <div class="rounded-xl p-4 shadow-border" wire:key="statistik-{{ $item['id'] }}">
                            <p class="font-semibold text-neutral-900">{{ $item['pertanyaan'] }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-4 text-xs text-neutral-500">
                                <span>Tipe: {{ $item['tipe'] ?? 'essay' }}</span>
                                <span>Total Jawaban: {{ $item['total_jawaban'] }}</span>
                                @if ($item['rata_rata_nilai'] !== null)
                                    <span class="font-semibold text-neutral-900">Rata-rata Nilai: {{ $item['rata_rata_nilai'] }}</span>
                                @endif
                            </div>

                            @if (! empty($item['distribusi_jawaban']))
                                <div class="mt-4 space-y-3">
                                    @foreach ($item['distribusi_jawaban'] as $dist)
                                        <div>
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-neutral-900">
                                                    {{ $dist['opsi'] }}
                                                    @if ($dist['nilai_numerik'] !== null)
                                                        <span class="text-neutral-500">(Nilai: {{ $dist['nilai_numerik'] }})</span>
                                                    @endif
                                                </span>
                                                <span class="text-neutral-600">{{ $dist['jumlah'] }} responden &middot; <span class="font-semibold text-neutral-900">{{ $dist['persentase'] }}%</span></span>
                                            </div>
                                            <div class="mt-1 h-2 w-full rounded-full bg-neutral-200">
                                                <div class="h-2 rounded-full bg-neutral-900" style="width: {{ $dist['persentase'] }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Modal: Tambah/Ubah Pertanyaan --}}
    @if ($showQuestionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4 py-8">
            <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">{{ $editingQuestionId ? 'Ubah Pertanyaan' : 'Tambah Pertanyaan' }}</h3>
                    <button type="button" wire:click="closeQuestionModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <form wire:submit="saveQuestion" class="space-y-4 p-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pertanyaan *</label>
                        <textarea wire:model="qPertanyaan" rows="3" placeholder="Masukkan pertanyaan survey" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('qPertanyaan') ring-2 ring-red-500 @enderror shadow-border"></textarea>
                        @error('qPertanyaan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tipe Pertanyaan *</label>
                        <x-searchable-select
                            model="qTipe"
                            :clearable="false"
                            :options="['essay' => 'Essay (Jawaban Bebas)', 'single_choice' => 'Pilihan Tunggal', 'multiple_choice' => 'Pilihan Ganda', 'likert' => 'Skala Likert']"
                        />
                    </div>

                    @if ($qTipe !== 'essay')
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opsi Jawaban *</label>
                            <p class="mb-2 text-xs text-neutral-500">Field nilai numerik opsional untuk skala likert atau perhitungan rata-rata.</p>
                            <div class="space-y-2">
                                @foreach ($qOptions as $index => $option)
                                    <div class="flex items-center gap-2" wire:key="option-{{ $index }}">
                                        <input
                                            type="text"
                                            wire:model="qOptions.{{ $index }}.opsi"
                                            placeholder="Opsi {{ $index + 1 }}"
                                            class="flex-1 rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                        />
                                        <input
                                            type="number"
                                            wire:model="qOptions.{{ $index }}.nilai_numerik"
                                            placeholder="Nilai"
                                            title="Nilai numerik (opsional)"
                                            class="w-24 rounded-lg px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                                        />
                                        <button type="button" wire:click="removeOption({{ $index }})" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:bg-rose-50">
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @error('qOptions') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('qOptions.*.opsi') <p class="mt-1.5 text-sm text-red-600">Setiap opsi wajib diisi.</p> @enderror
                            <button type="button" wire:click="addOption" class="mt-2 inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
                                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                                Tambah Opsi
                            </button>
                        </div>
                    @endif

                    <div class="flex items-center gap-3 border-t border-neutral-200 pt-4">
                        <button type="button" wire:click="closeQuestionModal" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                            Batal
                        </button>
                        <button type="submit" class="flex-1 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Hapus Pertanyaan --}}
    @if ($confirmingQuestionDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus pertanyaan?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan. Opsi jawaban terkait ikut terhapus.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteQuestion" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteQuestion" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
