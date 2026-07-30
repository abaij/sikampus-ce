@section('title', 'Tagihan — ' . config('app.name'))
@section('header_title', 'Tagihan')

@php
    $formatIdr = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $formatDate = fn ($v) => $v ? \Carbon\Carbon::parse($v)->translatedFormat('d F Y') : '-';
    $statusBadge = function (string $status, float $sisa) {
        if ($status === 'paid' || $sisa <= 0) {
            return ['icon' => 'check-circle', 'class' => 'bg-emerald-100 text-emerald-700', 'label' => 'Lunas'];
        }
        if ($status === 'expired') {
            return ['icon' => 'alert-circle', 'class' => 'bg-rose-100 text-rose-700', 'label' => 'Kedaluwarsa'];
        }
        return ['icon' => 'clock', 'class' => 'bg-amber-100 text-amber-700', 'label' => 'Belum Lunas'];
    };
    $modalTagihan = $this->payModalTagihan;
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($this->tagihanList->isEmpty())
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="receipt" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada tagihan</p>
            <p class="mt-1 text-sm text-neutral-500">Anda belum memiliki tagihan.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($this->tagihanList as $item)
                @php $badge = $statusBadge($item->status, (float) $item->sisa_tagihan); @endphp
                <div wire:key="tagihan-{{ $item->id }}" class="overflow-hidden rounded-2xl bg-white shadow-border">
                    <div class="border-b border-neutral-100 bg-sky-50 px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="mb-2 flex items-center gap-3">
                                    <h3 class="text-lg font-semibold text-neutral-900">{{ $item->no_tagihan }}</h3>
                                    <span class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium {{ $badge['class'] }}">
                                        <i data-lucide="{{ $badge['icon'] }}" class="h-3 w-3" aria-hidden="true"></i>
                                        {{ $badge['label'] }}
                                    </span>
                                </div>
                                @if ($item->semester)
                                    <p class="text-sm text-neutral-500">Semester: {{ $item->semester->nama }} ({{ $item->semester->kode }})</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-neutral-500">Total Tagihan</div>
                                <div class="text-xl font-bold text-neutral-900">{{ $formatIdr($item->total) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <div class="mb-1 text-sm text-neutral-500">Tanggal Tagihan</div>
                                <div class="font-medium text-neutral-900">{{ $formatDate($item->tanggal_tagihan) }}</div>
                            </div>
                            <div>
                                <div class="mb-1 text-sm text-neutral-500">Tanggal Jatuh Tempo</div>
                                <div class="font-medium text-neutral-900">{{ $formatDate($item->tanggal_jatuh_tempo) }}</div>
                            </div>
                            @if ($item->tanggal_pembayaran)
                                <div>
                                    <div class="mb-1 text-sm text-neutral-500">Tanggal Pembayaran</div>
                                    <div class="font-medium text-neutral-900">{{ $formatDate($item->tanggal_pembayaran) }}</div>
                                </div>
                            @endif
                        </div>

                        @if ($item->tagihanRinci->isNotEmpty())
                            <div class="mb-6">
                                <h4 class="mb-3 text-sm font-semibold text-neutral-700">Rincian Tagihan</h4>
                                <div class="space-y-2">
                                    @foreach ($item->tagihanRinci as $rinci)
                                        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2">
                                            <div>
                                                <div class="font-medium text-neutral-900">{{ $rinci->komponenBiaya->nama ?? '-' }}</div>
                                                <div class="text-xs text-neutral-500">{{ $rinci->komponenBiaya->kode ?? '-' }}</div>
                                            </div>
                                            <div class="font-semibold text-neutral-900">{{ $formatIdr($rinci->nominal) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="border-t border-neutral-100 pt-4">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-neutral-600">Total Tagihan:</span>
                                    <span class="font-semibold text-neutral-900">{{ $formatIdr($item->total) }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-neutral-600">Total Pembayaran:</span>
                                    <span class="font-semibold text-emerald-600">{{ $formatIdr($item->total_pembayaran) }}</span>
                                </div>
                                <div class="flex items-center justify-between border-t border-neutral-100 pt-2 text-sm">
                                    <span class="font-semibold text-neutral-900">Sisa Tagihan:</span>
                                    <span class="font-bold {{ $item->sisa_tagihan > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatIdr($item->sisa_tagihan) }}</span>
                                </div>
                                @if ($item->sisa_tagihan > $item->sisa_dapat_dibayar)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-neutral-600">Dalam verifikasi:</span>
                                        <span class="font-medium text-amber-700">{{ $formatIdr($item->sisa_tagihan - $item->sisa_dapat_dibayar) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($item->sisa_dapat_dibayar > 0)
                            <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                                <button
                                    type="button"
                                    wire:click="openBayarModal({{ $item->id }})"
                                    class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                                >
                                    <i data-lucide="banknote" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                                    Bayar tagihan
                                </button>
                            </div>
                        @endif

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

    @if ($modalTagihan)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="pr-8 text-lg font-semibold text-neutral-900">Bayar tagihan</h3>
                    <button type="button" wire:click="closeBayarModal" class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600" aria-label="Tutup">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="mt-1 text-sm text-neutral-500">{{ $modalTagihan->no_tagihan }}</p>
                <p class="mt-2 text-sm text-neutral-600">
                    Sisa dapat dibayarkan: <span class="font-semibold text-neutral-900">{{ $formatIdr($modalTagihan->sisa_dapat_dibayar) }}</span>
                </p>
                @if ($modalTagihan->sisa_dapat_dibayar < $modalTagihan->sisa_tagihan)
                    <p class="mt-1 text-xs text-amber-700">Ada pembayaran Anda yang masih menunggu verifikasi; sisa resmi belum lunas sampai disetujui.</p>
                @endif

                <div class="mt-5 space-y-3">
                    <div class="text-sm font-medium text-neutral-700">Jenis pembayaran</div>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 shadow-border has-[:checked]:bg-sky-50">
                        <input type="radio" wire:model="tipeBayar" value="penuh" class="text-sky-600" />
                        <span class="text-sm text-neutral-800">Pembayaran penuh (seluruh sisa)</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 shadow-border has-[:checked]:bg-sky-50">
                        <input type="radio" wire:model="tipeBayar" value="sebagian" class="text-sky-600" />
                        <span class="text-sm text-neutral-800">Pembayaran sebagian</span>
                    </label>
                </div>

                @if ($tipeBayar === 'sebagian')
                    <div class="mt-4">
                        <label class="text-sm font-medium text-neutral-700">Nominal pembayaran (Rp)</label>
                        <input
                            type="number"
                            wire:model="nominalPartial"
                            min="1"
                            max="{{ $modalTagihan->sisa_dapat_dibayar }}"
                            placeholder="Contoh: 500000"
                            class="mt-1 w-full rounded-lg px-3 py-2 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
                        />
                        @error('nominalPartial') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="mt-4">
                    <label class="text-sm font-medium text-neutral-700">Bukti pembayaran</label>
                    <input type="file" wire:model="buktiFile" accept="image/jpeg,image/png,image/jpg,application/pdf" class="mt-1 block w-full text-sm text-neutral-600" />
                    <p class="mt-1 text-xs text-neutral-500">JPG, PNG, atau PDF, maks. 10 MB</p>
                    @error('buktiFile') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeBayarModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">Batal</button>
                    <button
                        type="button"
                        wire:click="submitBayar"
                        wire:loading.attr="disabled"
                        wire:target="submitBayar,buktiFile"
                        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:opacity-50"
                    >
                        Kirim pembayaran
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
