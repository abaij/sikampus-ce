@section('title', 'Detail Pembayaran ' . $pembayaran->no_pembayaran . ' — ' . config('app.name'))
@section('header_title', 'Detail Pembayaran')
@section('header_subtitle', $pembayaran->no_pembayaran)
@section('header_icon', 'wallet')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Pembayaran', 'route' => $backUrl],
        ['label' => $pembayaran->no_pembayaran],
    ]])
@endsection

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Daftar
        </a>
        <a href="{{ route('admin.keuangan.pembayaran.edit', $pembayaran->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
    </div>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @error('approve') <p class="mb-4 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <button
            type="button"
            wire:click="confirmDelete"
            class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-medium text-rose-700 shadow-sm transition hover:bg-rose-50"
        >
            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
            Hapus
        </button>
        @if (! $pembayaran->approved_at)
            <button
                type="button"
                wire:click="approve"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700"
            >
                <i data-lucide="check-circle" class="h-4 w-4" aria-hidden="true"></i>
                ACC Pembayaran
            </button>
        @endif
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">Status Persetujuan</h3>
            @if ($pembayaran->approved_at)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex rounded-lg bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">Sudah ACC</span>
                    <span class="text-sm text-neutral-600">
                        {{ $pembayaran->approved_at->translatedFormat('d F Y H:i') }}
                        @if ($pembayaran->approved_by)
                            &middot; oleh {{ $pembayaran->approved_by }}
                        @endif
                    </span>
                </div>
            @else
                <span class="inline-flex rounded-lg bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">Belum ACC</span>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">Data Pembayaran</h3>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-500">Nominal</dt>
                    <dd class="mt-1 font-semibold text-neutral-900">Rp{{ number_format((float) $pembayaran->nominal, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Tanggal Pembayaran</dt>
                    <dd class="mt-1 text-neutral-900">{{ $pembayaran->tanggal_pembayaran?->translatedFormat('d F Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Metode</dt>
                    <dd class="mt-1 text-neutral-900">{{ $pembayaran->metode_pembayaran ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Dicatat Oleh</dt>
                    <dd class="mt-1 text-neutral-900">{{ $pembayaran->created_by ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-neutral-500">Keterangan</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-neutral-900">{{ $pembayaran->keterangan ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        @if ($pembayaran->tagihan)
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">Tagihan & Mahasiswa</h3>
                <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-neutral-500">No. Tagihan</dt>
                        <dd class="mt-1 font-medium text-neutral-900">{{ $pembayaran->tagihan->no_tagihan }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500">Total Tagihan</dt>
                        <dd class="mt-1 font-semibold text-neutral-900">Rp{{ number_format((float) $pembayaran->tagihan->total, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-500">Status Tagihan</dt>
                        <dd class="mt-1 text-neutral-900">{{ $pembayaran->tagihan->status }}</dd>
                    </div>
                    @if ($pembayaran->tagihan->semester)
                        <div>
                            <dt class="text-neutral-500">Semester</dt>
                            <dd class="mt-1 text-neutral-900">
                                {{ $pembayaran->tagihan->semester->nama }}
                                @if ($pembayaran->tagihan->semester->kode)
                                    ({{ $pembayaran->tagihan->semester->kode }})
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($pembayaran->tagihan->mahasiswa)
                        <div>
                            <dt class="text-neutral-500">Mahasiswa</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $pembayaran->tagihan->mahasiswa->nama }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-500">NIM</dt>
                            <dd class="mt-1 text-neutral-900">{{ $pembayaran->tagihan->mahasiswa->nim }}</dd>
                        </div>
                        @if ($pembayaran->tagihan->mahasiswa->prodi?->nama)
                            <div class="sm:col-span-2">
                                <dt class="text-neutral-500">Prodi</dt>
                                <dd class="mt-1 text-neutral-900">{{ $pembayaran->tagihan->mahasiswa->prodi->nama }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>

                @if ($pembayaran->tagihan->tagihanRinci->isNotEmpty())
                    <div class="mt-4">
                        <h4 class="mb-2 text-xs font-semibold text-neutral-600">Rincian Tagihan</h4>
                        <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                            @foreach ($pembayaran->tagihan->tagihanRinci as $rinci)
                                <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                    <span class="text-neutral-700">{{ $rinci->komponenBiaya->nama ?? $rinci->komponenBiaya->kode ?? 'Komponen' }}</span>
                                    <span class="font-medium text-neutral-900">Rp{{ number_format((float) $rinci->nominal, 0, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">Bukti Pembayaran</h3>
            @if ($buktiBayarUrl)
                <div class="space-y-3">
                    <a href="{{ $buktiBayarUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-sky-600 hover:text-sky-700">
                        <i data-lucide="external-link" class="h-4 w-4" aria-hidden="true"></i>
                        Buka file di tab baru
                    </a>
                    @if (str_ends_with(strtolower($pembayaran->bukti_bayar ?? ''), '.pdf'))
                        <div class="rounded-xl bg-neutral-50 p-4 shadow-border">
                            <div class="mb-2 flex items-center gap-2 text-sm text-neutral-600">
                                <i data-lucide="file-text" class="h-5 w-5" aria-hidden="true"></i>
                                Pratinjau PDF (gunakan tautan di atas jika tidak tampil)
                            </div>
                            <iframe title="Bukti pembayaran PDF" src="{{ $buktiBayarUrl }}" class="h-[480px] w-full rounded-lg border border-neutral-200 bg-white"></iframe>
                        </div>
                    @else
                        <div class="rounded-xl bg-neutral-50 p-4 shadow-border">
                            <img src="{{ $buktiBayarUrl }}" alt="Bukti pembayaran" class="mx-auto max-h-[480px] w-auto max-w-full rounded-lg object-contain" />
                        </div>
                    @endif
                </div>
            @else
                <p class="text-sm text-neutral-500">Tidak ada file bukti yang diunggah.</p>
            @endif
        </div>
    </div>

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus pembayaran?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="delete" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
