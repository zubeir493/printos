<div class="flex items-center w-full min-w-[120px] gap-2">
    <div class="flex-1 h-2 bg-gray-200 rounded-full dark:bg-gray-700 overflow-hidden">
        <div 
            class="h-full transition-all duration-500 {{ $state >= 100 ? 'bg-success-500' : ($state >= 50 ? 'bg-warning-500' : 'bg-primary-500') }}" 
            style="width: {{ min(100, max(0, $state)) }}%"
        ></div>
    </div>
    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ $state }}%
    </span>
</div>
