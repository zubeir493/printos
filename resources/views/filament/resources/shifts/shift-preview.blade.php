<div aria-live="polite" class="text-sm text-gray-600 dark:text-gray-400">
    @if ($preview['valid'])
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span><strong class="font-semibold text-gray-950 dark:text-white">{{ $preview['paid'] }}</strong> paid</span>
            <span>{{ $preview['span'] }} total</span>
            <span>{{ $preview['break'] }}</span>
            @if ($preview['overnight'])
                <x-filament::badge color="info">Overnight</x-filament::badge>
            @endif
        </div>
    @else
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $preview['message'] }}</p>
    @endif
</div>
