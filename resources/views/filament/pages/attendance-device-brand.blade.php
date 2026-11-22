<div>
    <div class="flex h-11 items-center gap-3">
        <x-filament::icon icon="heroicon-o-finger-print" class="h-8 w-8 text-primary-600" />
        <span class="text-xl font-semibold text-gray-950 dark:text-white">ZKTeco punch machine</span>
    </div>
    <div class="py-4">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">Attendance device</div>
        <p class="text-sm leading-5 text-gray-600 dark:text-gray-300">
            {{ $device['host'] }}:{{ $device['port'] }} · {{ $device['enabled'] ? ($device['processing_enabled'] ? 'Importing attendance' : 'Collecting punches') : 'Sync off' }}
        </p>
    </div>
</div>
