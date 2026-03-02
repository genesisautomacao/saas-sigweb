<x-filament-panels::page>
    <style>
        /* 1. O container geral do quadro */
        .meu-kanban-board {
            display: flex;
            overflow-x: auto;
            gap: 1rem;
            padding-bottom: 1rem;
            min-height: 65vh;
        }

        /* 2. A largura exata das colunas */
        .meu-kanban-coluna {
            flex: 0 0 280px; 
            display: flex;
            flex-direction: column;
            border-radius: 0.75rem;
            max-height: 75vh;
        }

        /* 3. A área de rolagem vertical dos cards */
        .meu-kanban-cards {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        /* 4. Barras de rolagem DO QUADRO PRINCIPAL */
        .meu-kanban-board::-webkit-scrollbar,
        .meu-kanban-cards::-webkit-scrollbar {
            height: 8px;
            width: 6px;
        }
        .meu-kanban-board::-webkit-scrollbar-thumb,
        .meu-kanban-cards::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .dark .meu-kanban-board::-webkit-scrollbar-thumb {
            background: #475569;
        }

        /* 5. OCULTA A BARRA DE ROLAGEM DO MENU (Agora usaremos as setas!) */
        .menu-status-scroll {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }
        .menu-status-scroll::-webkit-scrollbar {
            display: none; /* Chrome/Safari */
        }
    </style>

<div 
        x-data="{
            scrollLeft() { $refs.menuContainer.scrollBy({ left: -300, behavior: 'smooth' }); },
            scrollRight() { $refs.menuContainer.scrollBy({ left: 300, behavior: 'smooth' }); }
        }"
        class="mb-6 flex flex-col" 
    >
        <div 
            x-ref="menuContainer"
            class="flex overflow-x-auto gap-2 py-2 w-full menu-status-scroll border-b border-gray-200 dark:border-gray-800 pb-4"
        >
            @foreach($this->statuses as $status)
                <button 
                    type="button"
                    onclick="document.getElementById('col-{{ $status->id }}').scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })" 
                    class="shrink-0 px-4 py-1.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-primary-50 hover:text-primary-600 hover:border-primary-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 transition-all"
                >
                    {{ $status->name }}
                </button>
            @endforeach
        </div>

        <div class="flex justify-center items-center gap-4 mt-3">
            <button 
                @click="scrollLeft()" 
                type="button"
                class="flex items-center justify-center w-10 h-10 bg-white border border-gray-200 rounded-full shadow-sm text-gray-500 hover:text-primary-600 hover:bg-primary-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-primary-500"
                title="Rolar para a esquerda"
            >
                <x-heroicon-m-chevron-left class="w-6 h-6" />
            </button>

            <button 
                @click="scrollRight()" 
                type="button"
                class="flex items-center justify-center w-10 h-10 bg-white border border-gray-200 rounded-full shadow-sm text-gray-500 hover:text-primary-600 hover:bg-primary-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-primary-500"
                title="Rolar para a direita"
            >
                <x-heroicon-m-chevron-right class="w-6 h-6" />
            </button>
        </div>
    </div>

    <div class="meu-kanban-board">
        @foreach($this->statuses as $status)
            <div id="col-{{ $status->id }}" class="meu-kanban-coluna bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                
                <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-900 rounded-t-xl">
                    <h3 class="font-bold text-sm flex items-center gap-2 text-gray-800 dark:text-gray-100">
                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $status->color ?? '#9ca3af' }}"></span>
                        {{ $status->name }}
                    </h3>
                    <span class="text-xs font-medium text-gray-500 bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded-full">
                        {{ $this->leads->get($status->id)?->count() ?? 0 }}
                    </span>
                </div>

                <div class="meu-kanban-cards">
                    @foreach($this->leads->get($status->id) ?? [] as $lead)
                        
                        <div 
                            wire:click="mountAction('editLead', { lead_id: {{ $lead->id }} })"
                            class="p-4 bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:border-primary-500 hover:ring-1 hover:ring-primary-500 cursor-pointer transition-all"
                        >
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white">{{ $lead->name }}</h4>
                            </div>

                            @if($lead->contact_name)
                                <div class="text-xs text-gray-500 flex items-center gap-1 mb-1">
                                    <x-heroicon-o-user class="w-3 h-3 text-gray-400" />
                                    {{ $lead->contact_name }}
                                </div>
                            @endif

                            @if($lead->city)
                                <div class="text-xs text-gray-500 flex items-center gap-1 mb-3">
                                    <x-heroicon-o-map-pin class="w-3 h-3 text-gray-400" />
                                    {{ $lead->city }} @if($lead->state) / {{ $lead->state }} @endif
                                </div>
                            @endif

                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 flex justify-between items-center">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300 text-[10px] font-medium rounded-full">
                                    <x-heroicon-o-briefcase class="w-3 h-3" />
                                    {{ $lead->seller?->user?->name ?? 'Sem Vendedor' }}
                                </span>
                            </div>
                        </div>

                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>