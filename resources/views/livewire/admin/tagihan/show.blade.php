@php
    $statusBadge = function (string $status) {
        return match ($status) {
            'lunas' => ['Lunas', 'bg-emerald-100 text-emerald-700'],
            'dibayar_sebagian' => ['Dibayar sebagian', 'bg-sky-100 text-sky-800'],
            'kedaluwarsa' => ['Kedaluwarsa', 'bg-amber-100 text-amber-800'],
            default => ['Belum bayar', 'bg-rose-100 text-rose-700'],
        };
    };
    [$statusLabel, $statusClass] = $statusBadge($statusPembayaranAcc);
@endphp

@section('title', 'Detail Tagihan ' . ($tagihan->no_tagihan ?? '') . ' — ' . config('app.name'))
@section('header_title', 'Detail Tagihan')
@section('header_subtitle', $tagihan->no_tagihan ?? '—')
@section('header_icon', 'receipt')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Keuangan'],
        ['label' => 'Tagihan', 'route' => $backUrl],
        ['label' => $tagihan->no_tagihan ?? '—'],
    ]])
@endsection

@section('page_actions')
    <div class="flex items-center gap-2">
        <a
            href="{{ route('admin.keuangan.tagihan.edit', $tagihan->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
    </div>
@endsection

<div class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-700">Informasi Dasar</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold text-neutral-500">Mahasiswa</p>
                <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $tagihan->mahasiswa->nama ?? '—' }}</p>
                <p class="text-xs text-neutral-600">
                    {{ $tagihan->mahasiswa->nim ?? '' }}
                    @if ($tagihan->mahasiswa?->prodi?->nama)
                        &middot; {{ $tagihan->mahasiswa->prodi->nama }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold text-neutral-500">Semester</p>
                <p class="mt-1 text-sm text-neutral-900">
                    {{ $tagihan->semester->nama ?? '—' }}
                    @if ($tagihan->semester?->kode)
                        <span class="text-xs text-neutral-500">({{ $tagihan->semester->kode }})</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold text-neutral-500">Status (berdasarkan ACC)</p>
                <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                <p class="mt-1 text-xs text-neutral-500">
                    Status di sistem: <span class="font-medium text-neutral-600">{{ $tagihan->status }}</span>
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold text-neutral-500">Total Tagihan</p>
                <p class="mt-1 text-sm font-semibold text-neutral-900">Rp{{ number_format((float) $tagihan->total, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-neutral-500">Total Dibayar (Disetujui)</p>
                <p class="mt-1 text-sm font-semibold text-emerald-700">Rp{{ number_format($totalPembayaranDisetujui, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-neutral-500">Sisa (Menurut ACC)</p>
                <p class="mt-1 text-sm font-semibold text-neutral-900">Rp{{ number_format($sisaPembayaranDisetujui, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-neutral-500">Tanggal Tagihan</p>
                <p class="mt-1 text-sm text-neutral-900">{{ $tagihan->tanggal_tagihan?->translatedFormat('d M Y') ?? '—' }}</p>
            </div>
            @if ($tagihan->tanggal_jatuh_tempo)
                <div>
                    <p class="text-xs font-semibold text-neutral-500">Tanggal Jatuh Tempo</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ $tagihan->tanggal_jatuh_tempo->translatedFormat('d M Y') }}</p>
                </div>
            @endif
            @if ($tagihan->tanggal_pembayaran)
                <div>
                    <p class="text-xs font-semibold text-neutral-500">Tanggal Pembayaran</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ $tagihan->tanggal_pembayaran->translatedFormat('d M Y') }}</p>
                </div>
            @endif
            @if ($tagihan->keterangan)
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold text-neutral-500">Keterangan</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-neutral-900">{{ $tagihan->keterangan }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-700">Rincian Tagihan</h3>
        <div class="overflow-x-auto rounded-lg border border-neutral-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Komponen Biaya</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($tagihan->tagihanRinci as $rinci)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $rinci->komponenBiaya->nama ?? '—' }}</div>
                                @if ($rinci->komponenBiaya?->kode)
                                    <div class="text-xs text-neutral-500">Kode: {{ $rinci->komponenBiaya->kode }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-neutral-900">Rp{{ number_format((float) $rinci->nominal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-sky-50">
                    <tr>
                        <td class="px-4 py-3 text-right font-semibold text-neutral-900">Total:</td>
                        <td class="px-4 py-3 text-right font-bold text-sky-700">Rp{{ number_format((float) $tagihan->total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-700">Rincian Pembayaran Disetujui (ACC)</h3>
        @if ($tagihan->pembayaran->isNotEmpty())
            <div class="overflow-x-auto rounded-lg border border-neutral-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">No. Pembayaran</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Metode</th>
                            <th class="px-4 py-3">Disetujui</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($tagihan->pembayaran as $pembayaran)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-neutral-900">{{ $pembayaran->no_pembayaran }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-neutral-900">Rp{{ number_format((float) $pembayaran->nominal, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $pembayaran->tanggal_pembayaran?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $pembayaran->metode_pembayaran ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $pembayaran->approved_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-neutral-500">Belum ada pembayaran yang disetujui untuk tagihan ini.</p>
        @endif
    </div>
</div>
