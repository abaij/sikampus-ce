@section('title', 'Pembayaran — ' . config('app.name'))
@section('header_title', 'Pembayaran')

@php
    $formatIdr = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $formatDate = fn ($v) => $v ? \Carbon\Carbon::parse($v)->translatedFormat('d F Y') : '-';
@endphp

<div class="space-y-6">
    @if ($this->pembayaranList->isEmpty())
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="credit-card" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada pembayaran</p>
            <p class="mt-1 text-sm text-neutral-500">Anda belum memiliki riwayat pembayaran.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($this->pembayaranList as $item)
                <div wire:key="pembayaran-{{ $item->id }}" class="overflow-hidden rounded-2xl bg-white shadow-border">
                    <div class="border-b border-neutral-100 bg-emerald-50 px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="mb-2 flex items-center gap-3">
                                    <h3 class="text-lg font-semibold text-neutral-900">{{ $item->no_pembayaran }}</h3>
                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">
                                        <i data-lucide="check-circle" class="h-3 w-3" aria-hidden="true"></i>
                                        Terbayar
                                    </span>
                                </div>
                                @if ($item->tagihan)
                                    <p class="text-sm text-neutral-500">Tagihan: {{ $item->tagihan->no_tagihan }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-neutral-500">Nominal</div>
                                <div class="text-xl font-bold text-emerald-600">{{ $formatIdr($item->nominal) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <div class="mb-1 text-sm text-neutral-500">Tanggal Pembayaran</div>
                                <div class="flex items-center gap-2 font-medium text-neutral-900">
                                    <i data-lucide="calendar" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                                    {{ $formatDate($item->tanggal_pembayaran) }}
                                </div>
                            </div>
                            @if ($item->metode_pembayaran)
                                <div>
                                    <div class="mb-1 text-sm text-neutral-500">Metode Pembayaran</div>
                                    <div class="font-medium text-neutral-900">{{ $item->metode_pembayaran }}</div>
                                </div>
                            @endif
                            @if ($item->tagihan?->semester)
                                <div>
                                    <div class="mb-1 text-sm text-neutral-500">Semester</div>
                                    <div class="font-medium text-neutral-900">{{ $item->tagihan->semester->nama }} ({{ $item->tagihan->semester->kode }})</div>
                                </div>
                            @endif
                            @if ($item->tagihan)
                                <div>
                                    <div class="mb-1 text-sm text-neutral-500">Total Tagihan</div>
                                    <div class="font-medium text-neutral-900">{{ $formatIdr($item->tagihan->total) }}</div>
                                </div>
                            @endif
                        </div>

                        @if ($item->keterangan)
                            <div class="mt-4 border-t border-neutral-100 pt-4">
                                <div class="mb-1 text-sm text-neutral-500">Keterangan</div>
                                <div class="text-sm text-neutral-700">{{ $item->keterangan }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
