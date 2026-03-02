<div id="{{ $record->id }}" wire:click="recordClicked('{{ $record->id }}', {{ $record }})"
    class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow cursor-grab">

    <div class="flex justify-between items-start mb-2">
        <h4 class="font-bold text-sm text-gray-900 dark:text-white">{{ $record->name }}</h4>
    </div>

    @if($record->contact_name)
        <div class="text-xs text-gray-500 flex items-center gap-1 mb-1">
            <x-heroicon-o-user class="w-3 h-3 text-gray-400" />
            {{ $record->contact_name }}
        </div>
    @endif

    @if($record->city)
        <div class="text-xs text-gray-500 flex items-center gap-1 mb-3">
            <x-heroicon-o-map-pin class="w-3 h-3 text-gray-400" />
            {{ $record->city }} @if($record->state) / {{ $record->state }} @endif
        </div>
    @endif

    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
        <span
            class="inline-flex items-center gap-1 px-2 py-1 bg-primary-50 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400 text-[10px] font-medium rounded-full">
            <x-heroicon-o-briefcase class="w-3 h-3" />
            {{ $record->seller?->user?->name ?? 'Sem Vendedor' }}
        </span>
    </div>
</div>