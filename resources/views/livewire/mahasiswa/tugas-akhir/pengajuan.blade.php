@section('title', 'Form Pengajuan Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Form pengajuan tugas akhir')
@section('header_subtitle', 'Lengkapi judul, topik, dan lampiran untuk semester berjalan. Wajib mengontrak mata kuliah jenis TA pada KRS semester aktif yang disetujui.')

@section('breadcrumb')
    <a href="{{ route('mahasiswa.akhir-studi.tugas-akhir') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali ke daftar tugas akhir
    </a>
@endsection

@php
    $ctx = $this->ctx;
    $showForm = $ctx['eligible'] && (! $ctx['tugas_akhir'] || $ctx['can_edit']);
@endphp

<div class="space-y-6">
    @if ($ctx['semester_aktif'])
        <div class="rounded-xl bg-white px-4 py-3 shadow-border">
            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester aktif (pengajuan)</p>
            <p class="mt-1 text-base font-semibold text-neutral-900">{{ $ctx['semester_aktif']->nama }}</p>
            <p class="text-sm text-neutral-600">{{ $ctx['semester_aktif']->kode }}</p>
        </div>
    @endif

    @if (! $ctx['eligible'])
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-4">
            <div class="flex gap-3">
                <i data-lucide="alert-circle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" aria-hidden="true"></i>
                <div class="space-y-2">
                    <p class="font-semibold text-amber-950">Belum memenuhi syarat pengajuan</p>
                    <p class="text-sm text-amber-950/90">{{ $ctx['pesan_tidak_eligible'] }}</p>
                    <a href="{{ route('mahasiswa.krs.pengajuan') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-900 underline decoration-amber-700/40 underline-offset-2 hover:text-amber-950">
                        Ke halaman KRS / pengajuan
                        <i data-lucide="external-link" class="h-3.5 w-3.5" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if ($ctx['eligible'] && $ctx['krs_ta'])
        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-950">
            <div class="flex items-start gap-2">
                <i data-lucide="check-circle-2" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                <div>
                    <p class="font-semibold">Mata kuliah Tugas Akhir terkontrak (disetujui)</p>
                    <p class="mt-1">
                        {{ $ctx['krs_ta']->kelas->kurikulumMatkul->matkul->kode ?? '' }} — {{ $ctx['krs_ta']->kelas->kurikulumMatkul->matkul->nama ?? '' }}
                        @if ($ctx['krs_ta']->kelas->kurikulumMatkul->matkul->sks ?? null)
                            ({{ $ctx['krs_ta']->kelas->kurikulumMatkul->matkul->sks }} SKS)
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if ($ctx['eligible'] && $ctx['tugas_akhir'] && ! $ctx['can_edit'])
        <div class="rounded-xl bg-neutral-50 px-4 py-3 text-sm text-neutral-700 shadow-border">
            <p>
                Anda sudah memiliki pengajuan untuk semester ini dan tidak dalam mode perbaikan. Lihat status di
                <a href="{{ route('mahasiswa.akhir-studi.tugas-akhir') }}" class="font-semibold text-sky-700 underline">daftar tugas akhir</a>.
            </p>
        </div>
    @endif

    @if ($ctx['eligible'] && $ctx['tugas_akhir'] && in_array($ctx['tugas_akhir']->status, ['rejected', 'returned'], true) && $ctx['can_edit'])
        <div class="rounded-xl border border-rose-100 bg-rose-50/80 px-4 py-3 text-sm text-rose-950">
            <p class="font-semibold">Pengajuan perlu perbaikan</p>
            <p class="mt-1">Perbarui data di bawah lalu kirim ulang.</p>
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="submit" class="space-y-4 rounded-xl bg-white p-5 shadow-border">
            <h2 class="text-sm font-semibold text-neutral-800">{{ $ctx['tugas_akhir'] && $ctx['can_edit'] ? 'Perbaiki pengajuan' : 'Data pengajuan' }}</h2>

            <div class="flex items-center gap-3 rounded-lg bg-neutral-50/80 px-3 py-2 shadow-border">
                <input id="is_proposal" type="checkbox" wire:model="isProposal" class="h-4 w-4 rounded border-neutral-300 text-sky-600 focus:ring-sky-500" />
                <label for="is_proposal" class="text-sm text-neutral-800">Ini adalah pengajuan proposal (bukan judul final)</label>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-600">Topik / tema</label>
                <input type="text" wire:model="topik" placeholder="Ringkas topik penelitian" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10" />
                @error('topik') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">Topik (English)</label>
                <input type="text" wire:model="topikEn" placeholder="Topic in English (optional)" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10" />
                @error('topikEn') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">Judul (Bahasa Indonesia) <span class="text-rose-600">*</span></label>
                <input type="text" wire:model="judul" placeholder="Judul tugas akhir" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('judul') ring-2 ring-red-500 @enderror" />
                @error('judul') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">Judul (English)</label>
                <input type="text" wire:model="judulEn" placeholder="Title in English (optional)" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10" />
                @error('judulEn') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">Abstrak</label>
                <textarea wire:model="deskripsi" rows="5" placeholder="Ringkasan topik, tujuan, atau metode (opsional)" class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"></textarea>
                @error('deskripsi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-600">Lampiran (PDF, DOC, DOCX — maks. 12 MB)</label>
                <input type="file" wire:model="file" accept=".pdf,.doc,.docx" class="mt-1 block w-full text-sm text-neutral-700" />
                @error('file') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($ctx['tugas_akhir']?->file && $ctx['can_edit'] && ! $file)
                    <p class="mt-2 text-xs text-neutral-500">Berkas saat ini tetap dipakai jika Anda tidak memilih berkas baru.</p>
                @endif
            </div>
            <div class="flex justify-end border-t border-neutral-100 pt-4">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit,file"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading wire:target="submit"><i data-lucide="loader-2" class="mr-1 inline h-4 w-4 animate-spin" aria-hidden="true"></i></span>
                    {{ $ctx['tugas_akhir'] && $ctx['can_edit'] ? 'Kirim ulang pengajuan' : 'Kirim pengajuan' }}
                </button>
            </div>
        </form>
    @endif
</div>
