@php $items = $items ?? []; @endphp

<nav aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-neutral-500">
        <li>
            <a href="{{ route('admin.dashboard') }}" class="transition hover:text-neutral-700">Dashboard</a>
        </li>
        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-neutral-400" aria-hidden="true"></i>
                @if (! empty($item['route']))
                    <a href="{{ $item['route'] }}" class="transition hover:text-neutral-700">{{ $item['label'] }}</a>
                @else
                    <span class="font-medium text-neutral-700">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
