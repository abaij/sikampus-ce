@php
    $survey = $this->survey;
    $krs = $this->krs;
    $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
    $questions = $this->questions;
@endphp

@section('title', $survey->nama . ' — ' . config('app.name'))
@section('header_title', $survey->nama)

@section('breadcrumb')
    <a href="{{ route('mahasiswa.survey') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali ke Survey
    </a>
@endsection

<div class="space-y-6">
    <div class="rounded-xl bg-neutral-50 p-4 shadow-border">
        <h3 class="mb-1 font-semibold text-neutral-900">{{ $matkul->nama ?? '-' }}</h3>
        <div class="flex flex-wrap items-center gap-2 text-sm text-neutral-600">
            <span class="font-mono font-semibold text-sky-600">{{ $matkul->kode ?? '-' }}</span>
            <span>&bull;</span>
            <span>Kelas: {{ $krs->kelas->nama ?? '-' }}</span>
            <span>&bull;</span>
            <span>{{ $matkul->sks ?? 0 }} SKS</span>
        </div>
    </div>
    @if ($survey->keterangan)
        <p class="text-sm text-neutral-600">{{ $survey->keterangan }}</p>
    @endif

    @error('responses')
        <div class="flex gap-2 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0" aria-hidden="true"></i>
            {{ $message }}
        </div>
    @enderror

    @if ($questions->isEmpty())
        <div class="rounded-2xl bg-white p-8 text-center shadow-border">
            <i data-lucide="clipboard-list" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada pertanyaan untuk survey ini.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            @foreach ($questions as $idx => $q)
                @php $current = $responses[$q->id]['nilai_numerik'] ?? null; @endphp
                <div wire:key="q-{{ $q->id }}" class="rounded-2xl bg-white p-6 shadow-border">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sm font-semibold text-sky-600">{{ $idx + 1 }}</span>
                        <h3 class="flex-1 font-semibold text-neutral-900">{{ $q->pertanyaan }}</h3>
                    </div>

                    <div class="ml-11">
                        @if ($q->tipe === 'essay')
                            <textarea
                                wire:model="responses.{{ $q->id }}.nilai_text"
                                rows="4"
                                placeholder="Tulis jawaban Anda di sini..."
                                class="w-full rounded-lg px-4 py-3 shadow-border outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500"
                            ></textarea>
                        @elseif ($q->tipe === 'likert' || $q->tipe === 'single_choice')
                            <div class="space-y-2">
                                @foreach ($q->options as $option)
                                    @php $optionValue = $option->nilai_numerik ?? $option->id; @endphp
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 shadow-border transition hover:bg-neutral-50">
                                        <input
                                            type="radio"
                                            wire:model="responses.{{ $q->id }}.nilai_numerik"
                                            value="{{ $optionValue }}"
                                            class="h-4 w-4 text-sky-600 focus:ring-sky-500"
                                        />
                                        <span class="text-neutral-700">{{ $option->opsi }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($q->tipe === 'multiple_choice')
                            <div class="space-y-2">
                                @foreach ($q->options as $option)
                                    @php $optionValue = $option->nilai_numerik ?? $option->id; @endphp
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg p-3 shadow-border transition hover:bg-neutral-50">
                                        <input
                                            type="checkbox"
                                            wire:click="setNumerik({{ $q->id }}, {{ $current === $optionValue ? 0 : $optionValue }})"
                                            @checked($current === $optionValue)
                                            class="h-4 w-4 rounded text-sky-600 focus:ring-sky-500"
                                        />
                                        <span class="text-neutral-700">{{ $option->opsi }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <input
                                type="number"
                                wire:model="responses.{{ $q->id }}.nilai_numerik"
                                min="0"
                                placeholder="Masukkan nilai numerik"
                                class="w-full rounded-lg px-4 py-3 shadow-border outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500"
                            />
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-2 font-semibold text-neutral-900">Feedback (Opsional)</h3>
                <p class="mb-4 text-sm text-neutral-600">Berikan masukan, saran, atau komentar tambahan mengenai mata kuliah ini</p>
                <textarea
                    wire:model="feedback"
                    rows="6"
                    placeholder="Tulis feedback Anda di sini..."
                    class="w-full resize-none rounded-lg px-4 py-3 text-neutral-800 shadow-border outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500"
                ></textarea>
                <p class="mt-2 text-xs text-neutral-500">Feedback ini akan disimpan bersama dengan jawaban survey Anda</p>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('mahasiswa.survey') }}" class="rounded-lg bg-neutral-100 px-6 py-3 text-sm font-medium text-neutral-700 transition hover:bg-neutral-200">
                    Batal
                </a>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                    Simpan Survey
                </button>
            </div>
        </form>
    @endif
</div>
