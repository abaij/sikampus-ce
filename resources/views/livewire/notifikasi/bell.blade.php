@php
    $unreadCount = $this->unreadCount;
@endphp

<div x-data @click.away="$wire.close()" wire:poll.45s class="relative">
    <button
        type="button"
        wire:click="toggle"
        aria-label="Notifikasi"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        class="relative flex h-9 w-9 items-center justify-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
    >
        <i data-lucide="bell" class="h-5 w-5" aria-hidden="true"></i>
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-none text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-30 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl bg-white shadow-border-lg">
            <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3">
                <p class="text-sm font-semibold text-neutral-900">Notifikasi</p>
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        class="inline-flex items-center gap-1 text-xs font-medium text-sky-600 transition hover:text-sky-700"
                    >
                        <i data-lucide="check-check" class="h-3.5 w-3.5" aria-hidden="true"></i>
                        Tandai semua dibaca
                    </button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto" wire:loading.remove wire:target="toggle,openItem,markAllAsRead">
                @forelse ($this->items as $item)
                    <button
                        type="button"
                        wire:key="notif-{{ $item->id }}"
                        wire:click="openItem({{ $item->id }})"
                        class="flex w-full items-start gap-2.5 border-b border-neutral-50 px-4 py-3 text-left transition last:border-b-0 hover:bg-neutral-50 {{ $item->dibaca_pada === null ? 'bg-sky-50/60' : '' }}"
                    >
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $item->dibaca_pada === null ? 'bg-sky-500' : 'bg-transparent' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-neutral-900">{{ $item->judul }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-neutral-600">{{ $item->pesan }}</span>
                            <span class="mt-1 block text-[11px] text-neutral-400">{{ $this->formatWaktuLalu($item->created_at) }}</span>
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-neutral-500">Belum ada notifikasi</p>
                @endforelse
            </div>

            <div wire:loading wire:target="toggle,openItem,markAllAsRead" class="flex items-center justify-center gap-2 px-4 py-8 text-sm text-neutral-500">
                <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                Memuat...
            </div>
        </div>
    @endif
</div>
