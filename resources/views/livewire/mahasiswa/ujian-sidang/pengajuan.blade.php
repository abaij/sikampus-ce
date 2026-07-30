@section('title', 'Pengajuan Ujian Sidang — ' . config('app.name'))
@section('header_title', 'Pengajuan ujian sidang')
@section('header_subtitle', 'Pilih tugas akhir yang judulnya sudah disetujui, semester ujian sidang, dan unggah laporan tugas akhir/skripsi (PDF, DOC, atau DOCX).')

@section('breadcrumb')
    <a href="{{ route('mahasiswa.akhir-studi.ujian-sidang') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali ke ujian sidang
    </a>
@endsection

@php
    $ctx = $this->ctx;
    $canForm = $ctx['has_tugas_akhir'] && $ctx['eligible_pengajuan'] && $ctx['tugas_akhir_approved']->isNotEmpty();
@endphp

<div class="mx-auto max-w-lg space-y-6">
    @if (! $canForm)
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-4 text-sm text-amber-950">
            <p class="font-semibold">Form tidak tersedia</p>
            <p class="mt-2">
                @if (! $ctx['has_tugas_akhir'])
                    Anda belum memiliki data tugas akhir.
                @else
                    {{ $ctx['pesan_tidak_eligible'] ?? 'Belum ada tugas akhir dengan status disetujui.' }}
                @endif
            </p>
            <a href="{{ route('mahasiswa.akhir-studi.ujian-sidang') }}" class="mt-3 inline-flex items-center gap-1 font-semibold text-amber-900 underline">
                Kembali
                <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
            </a>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4 rounded-xl bg-white p-5 shadow-border">
            <div>
                <label class="block text-xs font-medium text-neutral-600">Tugas akhir (judul disetujui) <span class="text-rose-600">*</span></label>
                <x-searchable-select
                    model="idTugasAkhir"
                    :options="$this->taOptions"
                    :live="true"
                    :clearable="false"
                    placeholder="Cari atau pilih tugas akhir…"
                />
                @error('idTugasAkhir') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">Semester ujian sidang <span class="text-rose-600">*</span></label>
                <x-searchable-select
                    model="idSemester"
                    :options="$this->semesterOptions"
                    :clearable="false"
                    placeholder="{{ $idTugasAkhir ? 'Cari atau pilih semester…' : 'Pilih tugas akhir terlebih dahulu' }}"
                />
                @error('idSemester') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($idTugasAkhir !== '' && count($this->semesterOptions) === 0)
                    <p class="mt-1 text-xs text-amber-700">Tidak ada semester tersisa untuk tugas akhir ini.</p>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">File laporan tugas akhir / skripsi <span class="text-rose-600">*</span></label>
                <input type="file" wire:model="fileLaporan" accept=".pdf,.doc,.docx" class="mt-1 block w-full text-sm text-neutral-700" />
                <p class="mt-1 text-xs text-neutral-500">Maks. 12 MB. Format: PDF, DOC, atau DOCX.</p>
                @error('fileLaporan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end border-t border-neutral-100 pt-4">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit,fileLaporan"
                    class="inline-flex items-center justify-center rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Kirim pengajuan
                </button>
            </div>
        </form>
    @endif
</div>
