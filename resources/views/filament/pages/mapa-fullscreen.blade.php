<div class="relative w-screen h-screen overflow-hidden bg-gray-100 dark:bg-gray-900 font-sans text-gray-800">

    {{-- 🛡️ ÁREA PROTEGIDA DO LIVEWIRE (Mapa e Controles JS não serão apagados) 🛡️ --}}
    <div wire:ignore>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>

        <style>
            .ol-zoom {
                top: 6rem !important;
                left: 1.25rem !important;
            }

            #layers-panel {
                will-change: top, left;
                z-index: 50;
                width: 320px !important;
                min-width: 320px !important;
                max-width: 320px !important;
            }

            /* Deixa o arrasto 100% fluido desligando o blur pesadíssimo temporariamente */
            .dragging-now {
                backdrop-filter: none !important;
                opacity: 0.95;
            }

            /* Scrollbar fininha e elegante para a janela de camadas */
            .custom-scrollbar::-webkit-scrollbar {
                width: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background-color: rgba(156, 163, 175, 0.5);
                border-radius: 10px;
            }

            /* ... suas outras regras ... */
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background-color: rgba(156, 163, 175, 0.5);
                border-radius: 10px;
            }

            /* Tooltip de Medição */
            .ol-tooltip {
                position: relative;
                background: rgba(0, 0, 0, 0.85);
                border-radius: 8px;
                color: white;
                padding: 6px 10px;
                opacity: 1;
                white-space: nowrap;
                font-size: 13px;
                font-weight: bold;
                border: 1px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
        </style>

        {{-- O MAPA --}}
        <div id="sigweb-map" class="absolute inset-0 z-0 w-full h-full"></div>

        {{-- TOOLTIP DE MEDIÇÃO (Escondido) --}}
        <div id="measure-tooltip" class="ol-tooltip" style="display: none;"></div>

        {{-- BARRA SUPERIOR E CONTROLES --}}
        <div class="absolute top-4 left-0 w-full px-4 z-40 pointer-events-none flex items-start justify-between">

            <div class="pointer-events-auto">
                <a href="/app/{{ $tenantSlug }}"
                    class="bg-white dark:bg-gray-800/95 shadow-lg border border-gray-200 dark:border-gray-700 px-4 py-2.5 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition-all flex items-center gap-2">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                    <span class="hidden sm:inline">Painel</span>
                </a>
            </div>

            <div
                class="flex items-center gap-2 pointer-events-auto bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 rounded-2xl max-w-2xl w-full mx-4">

                {{-- Busca Integrada com AUTOCOMPLETE (Alpine.js) --}}
                <div x-data="loteSearch()"
                    class="relative flex items-center flex-1 min-w-[200px] border-r border-gray-100 dark:border-gray-700 px-2"
                    x-ref="inputWrapper">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 mr-2" />
                    <input type="text" x-model="termo" @input.debounce.500ms="buscar(); posicionarDropdown()"
                        @keydown.enter="buscar()" x-ref="inputField"
                        placeholder="Buscar lote ou Cód Tributário exato..."
                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 dark:text-gray-200 outline-none">

                    {{-- Spinner de Carregando (Pequenino no canto) --}}
                    <div x-show="loading" style="display: none;" class="absolute right-3">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-500"></div>
                    </div>

                    {{-- Lista Suspensa de Múltiplas Escolhas --}}
                    <div x-show="resultados.length > 0" style="display: none;" @click.outside="resultados = []"
                        x-ref="dropdown" :style="dropdownStyle"
                        class="fixed bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-[9999]">
                        <ul class="overflow-y-auto custom-scrollbar" :style="`max-height: ${dropdownMaxHeight}px`">

                            <template x-for="res in resultados" :key="res.id + '_' + res.codigo">
                                <li @click="voarPara(res)"
                                    class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer transition-colors flex items-start gap-3">
                                    <x-heroicon-o-map-pin class="w-5 h-5 text-primary-500 flex-shrink-0 mt-0.5" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-800 dark:text-gray-100">
                                            Lote: <span x-text="res.lote"></span>
                                            <span class="text-gray-400 font-normal mx-1">|</span>
                                            Quadra: <span x-text="res.quadra"></span>
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            Cód Tributário: <span class="font-semibold" x-text="res.codigo"></span>
                                        </span>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                </div>

                <div class="flex items-center gap-1 px-1">

                    {{-- ✏️ DROPDOWN DE CRIAÇÃO --}}
                    <div x-data="{ openDraw: false }" class="relative">
                        <button @click="openDraw = !openDraw" @click.outside="openDraw = false" title="Desenhar no Mapa"
                            class="p-2 hover:bg-primary-100 dark:hover:bg-primary-900/20 rounded-xl text-primary-600 dark:text-primary-400 transition-colors focus:outline-none flex items-center gap-1">
                            <x-heroicon-o-pencil-square class="w-5 h-5" />
                            <x-heroicon-o-chevron-down class="w-3 h-3" />
                        </button>

                        {{-- Menu de Opções --}}
                        <div x-show="openDraw" style="display: none;"
                            class="fixed left-0 mt-2 w-[300px] bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                            <div
                                class="px-3 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Criar
                                    Artefato</span>
                            </div>
                            <div class="py-1 flex flex-col">
                                <button onclick="enableDrawing('lote')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-stop class="w-4 h-4" /> Lote (Polígono)
                                </button>
                                <button onclick="enableDrawing('edificacao')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-home class="w-4 h-4" /> Edificação (Polígono)
                                </button>
                                <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                <button onclick="enableDrawing('poste')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-light-bulb class="w-4 h-4" /> Poste (Ponto)
                                </button>
                                <button onclick="enableDrawing('arvore')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-sparkles class="w-4 h-4" /> Árvore (Ponto)
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Separador --}}
                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                    <button id="btn-measure-line" title="Medir Distância"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors focus:outline-none">
                        <x-heroicon-o-arrows-right-left class="w-5 h-5" />
                    </button>

                    <button id="btn-measure-area" title="Medir Área"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors focus:outline-none">
                        <x-heroicon-o-view-columns class="w-5 h-5" />
                    </button>

                    {{-- Separador e Botão de Satélite --}}
                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                    <button id="btn-satelite" title="Alternar Mapa Base"
                        class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-2 font-bold text-sm">
                        <x-heroicon-o-globe-americas class="w-5 h-5" />
                        <span id="satelite-text" class="hidden md:inline">Satélite</span>
                    </button>

                    <button id="btn-toggle-layers"
                        class="px-4 py-2 bg-primary-50 dark:bg-primary-900/20 hover:bg-primary-100 rounded-xl text-primary-600 dark:text-primary-400 font-bold text-sm flex items-center gap-2">
                        <x-heroicon-o-square-3-stack-3d class="w-5 h-5" />
                        <span class="hidden md:inline">Camadas</span>
                    </button>
                </div>
            </div>
            <div class="w-10"></div>
        </div>

        {{-- JANELA DE CAMADAS (ACORDEÃO ALPINE.JS - LARGURA BLOQUEADA) --}}
        <div id="layers-panel" style="top: 80px; left: calc(100vw - 340px);" x-data="{ activeTab: 'base' }"
            class="absolute bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-2xl rounded-2xl border-2 border-gray-200/50 dark:border-gray-700/50 flex-shrink-0 flex flex-col overflow-hidden z-20 pointer-events-auto transition-colors">

            <div class="bg-gray-100/50 dark:bg-gray-800/50 border-b border-gray-200/50 dark:border-gray-700/50 px-4 py-3 flex justify-between items-center cursor-grab active:cursor-grabbing"
                id="layers-panel-header">
                <h3
                    class="font-bold text-sm text-gray-700 dark:text-gray-200 flex items-center gap-2 uppercase tracking-wider">
                    <x-heroicon-o-bars-3 class="w-4 h-4 text-gray-500" /> Camadas do Mapa
                </h3>
            </div>

            <div class="overflow-y-auto overflow-x-hidden max-h-[65vh] custom-scrollbar">

                {{-- GRUPO 1: CADASTRO BASE --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'base' ? '' : 'base'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Cadastro Base</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'base' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'base'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">

                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                            <input type="checkbox" checked data-layer="perimetros"
                                class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-red-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Perímetros/Limites</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="bairros"
                                class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-blue-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Bairros</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="quadras"
                                class="layer-toggle rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-orange-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                </div>
                                <span class="layer-text truncate">Quadras</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full"
                            title="Visível apenas de perto">
                            <input type="checkbox" data-layer="lotes"
                                class="layer-toggle rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                </div>
                                <span class="layer-text truncate">Lotes</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full"
                            title="Visível apenas de perto">
                            <input type="checkbox" data-layer="edificacoes"
                                class="layer-toggle rounded border-gray-300 text-amber-700 focus:ring-amber-700 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-amber-700 rounded-sm opacity-80 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Edificações</span>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- GRUPO 2: ZONEAMENTO URBANO (DINÂMICO) --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'zonas' ? '' : 'zonas'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Zoneamento Urbano</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'zonas' ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="activeTab === 'zonas'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full overflow-hidden">
                        @foreach($zonasTipos as $zona)
                            @php $rgbLimpo = str_replace(['(', ')'], '', $zona['rgb']); @endphp
                            <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"
                                title="{{ $zona['name'] }}">
                                <input type="checkbox" data-layer="zonas" data-zona-sigla="{{ $zona['sigla'] }}"
                                    class="zona-toggle rounded border-gray-400 shadow-sm w-4 h-4 flex-shrink-0"
                                    style="color: rgb({{ $rgbLimpo }});">
                                <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-2">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10"
                                        style="background-color: rgb({{ $rgbLimpo }});"></div>
                                    <span
                                        class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">{{ $zona['sigla'] }}
                                        - {{ $zona['name'] }}</span>
                                </span>
                            </label>
                        @endforeach

                        @if(empty($zonasTipos))
                            <p class="text-xs text-gray-400">Nenhuma zona cadastrada.</p>
                        @endif
                    </div>
                </div>

                {{-- GRUPO 3: INFRAESTRUTURA --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'infra' ? '' : 'infra'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Infraestrutura</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'infra' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'infra'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                            <input type="checkbox" data-layer="logradouros"
                                class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div>
                                <span class="layer-text truncate">Logradouros</span>
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

    </div>

    {{-- ⚡ ÁREA GERENCIADA PELO LIVEWIRE (A Ficha do Imóvel) ⚡ --}}
    <div x-data="{ open: @entangle('showFicha') }" x-show="open"
        class="fixed inset-y-0 right-0 z-50 w-[300px] bg-white dark:bg-gray-800 shadow-2xl border-l border-gray-200 dark:border-gray-700 transform transition-transform duration-300 flex flex-col"
        x-transition:enter="translate-x-0" x-transition:enter-end="translate-x-0" x-transition:leave="translate-x-0"
        x-transition:leave-end="translate-x-0" style="display: none; width: 300px !important;">

        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <x-heroicon-o-map-pin class="w-5 h-5 text-primary-600" /> Detalhes do Lote
            </h2>
            {{-- MÁGICA DE FECHAMENTO AQUI --}}
            <button wire:click="fecharFicha" @click="open = false"
                class="text-gray-400 hover:text-red-500 transition-colors">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto">
            @if($loteAtivoId)

                {{-- 1. QUADRO RESUMO DO LOTE --}}
                <div class="mb-6 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-xl border border-gray-200 dark:border-gray-600">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Lote / Inscrição</p>
                    <p class="text-xl font-black text-gray-800 dark:text-white">{{ $loteAtivoNome }}</p>
                    <p class="text-xs text-gray-500 mt-1">ID Sistema: #{{ $loteAtivoId }}</p>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold flex items-center gap-1">
                                <x-heroicon-o-building-office-2 class="w-3 h-3" /> Face Principal
                            </p>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                {{ number_format($loteFacePrincipal, 2, ',', '.') }} metros
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold flex items-center gap-1">
                                <x-heroicon-o-map class="w-3 h-3" /> Área Lote Geo
                            </p>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                {{ number_format($loteAreaGeo, 2, ',', '.') }} m²
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold flex items-center gap-1">
                                <x-heroicon-o-home-modern class="w-3 h-3" /> Área Construída
                            </p>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                {{ number_format($loteAreaConstruida, 2, ',', '.') }} m²
                            </p>
                        </div>
                    </div>
                </div>

                {{-- 2. AÇÕES ESPECÍFICAS (Ex: Lote) --}}
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase pt-6 mb-4">Ações do Lote</h3>
                <div class="space-y-3">
                    <button wire:click="abrirModalUnidades"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Ver
                            Unidades Imobiliárias</span>
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>
                    <button
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Consulta
                            e Viabilidade</span>
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>
                    <button
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Adicionar
                            Edificação</span>
                        <x-heroicon-o-plus class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>
                </div>

            @endif
        </div>

        {{-- 3. RODAPÉ PADRÃO (Ações Universais para todos os Artefatos) --}}
        @if($loteAtivoId)
            <div
                class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-row items-center justify-center gap-3">
                <button
                    class="w-50 gap-1 px-4 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 dark:text-blue-400 font-bold rounded-xl transition-colors border border-blue-100 dark:border-blue-900/30">
                    <x-heroicon-o-pencil-square class="w-5 h-5" />
                </button>

                {{-- CHAMA A NOSSA NOVA ACTION DE EXCLUSÃO DO FILAMENT --}}
                <button wire:click="mountAction('deletarArtefato')"
                    class="w-50 gap-1 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/20 dark:hover:bg-red-900/40 dark:text-red-400 font-bold rounded-xl transition-colors border border-red-100 dark:border-red-900/30">
                    <x-heroicon-o-trash class="w-5 h-5" />
                </button>
            </div>
        @endif

    </div>

    {{-- ⚡ MODAL DAS UNIDADES IMOBILIÁRIAS (LIVEWIRE/ALPINE) ⚡ --}}
    <div x-data="{ modalOpen: @entangle('showModalUnidades') }" x-show="modalOpen" style="display: none;"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 ms-2 ps-[320px] bg-black/50 backdrop-blur-sm">

        <div x-show="modalOpen" @click.outside="modalOpen = false" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[85vh]">

            <div
                class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
                <h2 class="text-lg font-bold flex items-center gap-2 text-gray-800 dark:text-gray-100">
                    <x-heroicon-o-home-modern class="w-5 h-5 text-primary-600" /> Unidades Imobiliárias do Lote
                    #{{ $loteAtivoNome }}
                </h2>
                <button wire:click="fecharModalUnidades" class="text-gray-400 hover:text-red-500 transition-colors">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <div class="p-6 overflow-y-auto">
                @if(count($unidadesImobiliarias) > 0)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Inscrição Imobiliária</th>
                                    <th scope="col" class="px-6 py-3">Cód. Tributário</th>
                                    <th scope="col" class="px-6 py-3">Proprietário (Responsável)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($unidadesImobiliarias as $unidade)
                                    <tr
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">
                                            {{ $unidade['inscricao_imobiliaria'] ?? 'Não informada' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ $unidade['codigo_imovel_tributario'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold">
                                                JD</div>
                                            <span>João da Silva (Fictício)</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-10">
                        <x-heroicon-o-document-magnifying-glass class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500 dark:text-gray-400">Nenhuma unidade imobiliária cadastrada para este lote.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 🛡️ SCRIPT PROTEGIDO DO LIVEWIRE 🛡️ --}}
    <div wire:ignore>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const centerLon = {{ $mapLon }};
                const centerLat = {{ $mapLat }};
                const initialZoom = {{ $mapZoom }};
                const tenantId = {{ $tenantId }};
                let zonasAtivas = [];

                // Mantenha toda a sua configuração do map, layerConfigs e dragElement aqui...
                const view = new ol.View({ center: ol.proj.fromLonLat([centerLon, centerLat]), zoom: initialZoom, maxZoom: 22 });

                // 🌍 DEFINIÇÃO DOS MAPAS BASE (Raster)
                const osmLayer = new ol.layer.Tile({ source: new ol.source.OSM(), zIndex: 0 });

                const esriLayer = new ol.layer.Tile({
                    source: new ol.source.XYZ({
                        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                        // A MÁGICA 1: Dizemos que a ESRI só vai até o 18. 
                        // Acima disso, o OpenLayers fará o "Zoom Digital" e não dará mais o erro de "Map data not yet available"!
                        maxZoom: 18,
                        crossOrigin: 'anonymous'
                    }),
                    visible: false,
                    zIndex: 1
                });

                const ortofotoLayer = new ol.layer.Tile({
                    source: new ol.source.XYZ({
                        // A MÁGICA 2: Apontamos para a pasta exata que você tem no seu servidor (santa_cecilia)
                        // (No futuro, você pode trocar 'santa_cecilia' por uma variável do BD que guarde o nome exato da pasta)
                        url: '/mapas/{{$tenantSlug}}/{z}/{x}/{y}.png',
                        minZoom: 12,
                        // Permitimos que o mosaico chegue até o zoom 22
                        maxZoom: 22,
                        crossOrigin: 'anonymous'
                    }),
                    visible: false,
                    zIndex: 2
                });

                const layerConfigs = {

                    'perimetros': { z: 10, minZoom: 0, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#ef4444', width: 3 }), fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.05)' }) }) },

                    // A MÁGICA DAS ZONAS ACONTECE AQUI:
                    'zonas': {
                        z: 20,
                        minZoom: 0,
                        style: function (feature) {
                            const sigla = feature.get('sigla');
                            const rgbBruto = feature.get('rgb'); // Ex: "(255,0,197)"

                            // Se a sigla deste polígono não estiver no array de ativas, retorna null (fica invisível)
                            if (!zonasAtivas.includes(sigla)) {
                                return null;
                            }

                            // Limpa os parênteses do banco para montar a cor
                            const rgbLimpo = rgbBruto ? rgbBruto.replace(/[()]/g, '') : '150,150,150';

                            return new ol.style.Style({
                                stroke: new ol.style.Stroke({ color: `rgb(${rgbLimpo})`, width: 2, lineDash: [4, 4] }),
                                fill: new ol.style.Fill({ color: `rgba(${rgbLimpo}, 0.25)` }),
                                // Bônus: Escreve a Sigla no meio da zona!
                                text: new ol.style.Text({
                                    text: sigla, font: 'bold 14px Arial', fill: new ol.style.Fill({ color: '#333' }),
                                    stroke: new ol.style.Stroke({ color: '#fff', width: 3 })
                                })
                            });
                        }
                    },

                    'bairros': { z: 30, minZoom: 0, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#3b82f6', width: 2 }), fill: new ol.style.Fill({ color: 'rgba(59, 130, 246, 0.1)' }) }) },

                    'quadras': { z: 40, minZoom: 13, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#f97316', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(249, 115, 22, 0.2)' }) }) },

                    'logradouros': { z: 50, minZoom: 14, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#475569', width: 3 }) }) },

                    /* lotes */
                    'lotes': {
                        z: 60,
                        minZoom: 15.5,
                        style: function (feature, resolution) {
                            // Descobre o nível de zoom atual
                            const zoom = map.getView().getZoomForResolution(resolution);

                            const style = new ol.style.Style({
                                stroke: new ol.style.Stroke({ color: '#10b981', width: 1 }),
                                fill: new ol.style.Fill({ color: 'rgba(16, 185, 129, 0.15)' })
                            });

                            // Só renderiza o texto se o zoom for 18 ou maior (câmera bem baixa)
                            if (zoom >= 18) {
                                style.setText(new ol.style.Text({
                                    text: feature.get('name') ? feature.get('name').toString() : '',
                                    font: 'bold 12px Arial, sans-serif',
                                    fill: new ol.style.Fill({ color: '#064e3b' }), // Verde escuro
                                    stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), // Borda branca
                                    overflow: true // Garante que o texto apareça mesmo em lotes muito pequenos
                                }));
                            }
                            return style;
                        }
                    },

                    'edificacoes': { z: 70, minZoom: 16, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#b45309', width: 1 }), fill: new ol.style.Fill({ color: 'rgba(180, 83, 9, 0.5)' }) }) },
                };

                const map = new ol.Map({ target: 'sigweb-map', layers: [osmLayer, esriLayer, ortofotoLayer], view: view });

                // 🛑 MÁGICA: Desativa o zoom ao dar duplo clique (para podermos fechar polígonos em paz!)
                const dblClickZoom = map.getInteractions().getArray().find(i => i instanceof ol.interaction.DoubleClickZoom);
                if (dblClickZoom) map.removeInteraction(dblClickZoom);

                const loadedLayers = {};

                // 🛰️ Lógica do Botão Satélite / Mapa Base
                let showSat = false;
                const btnSatelite = document.getElementById('btn-satelite');
                const sateliteText = document.getElementById('satelite-text');

                if (btnSatelite) {
                    btnSatelite.addEventListener('click', () => {
                        showSat = !showSat;

                        // Alterna as visibilidades
                        osmLayer.setVisible(!showSat);
                        esriLayer.setVisible(showSat);
                        ortofotoLayer.setVisible(showSat);

                        // Muda a cor e o texto do botão para dar feedback visual
                        if (showSat) {
                            btnSatelite.classList.add('bg-primary-50', 'text-primary-600', 'dark:bg-primary-900/20', 'dark:text-primary-400');
                            btnSatelite.classList.remove('text-gray-600', 'dark:text-gray-300');
                            if (sateliteText) sateliteText.innerText = 'Mapa Open';
                        } else {
                            btnSatelite.classList.remove('bg-primary-50', 'text-primary-600', 'dark:bg-primary-900/20', 'dark:text-primary-400');
                            btnSatelite.classList.add('text-gray-600', 'dark:text-gray-300');
                            if (sateliteText) sateliteText.innerText = 'Satélite';
                        }
                    });
                }

                // Lógica para baixar e desenhar a camada
                const fetchAndDrawLayer = (layerName, checkboxElement) => {
                    if (loadedLayers[layerName]) {
                        loadedLayers[layerName].setVisible(true);
                        return;
                    }

                    // Pega apenas o span do texto, ignorando a bolinha de cor!
                    const textSpan = checkboxElement.nextElementSibling.querySelector('.layer-text');
                    let originalText = '';

                    if (textSpan) {
                        originalText = textSpan.innerHTML;
                        textSpan.innerHTML = 'Carregando...';
                        textSpan.classList.add('animate-pulse', 'text-primary-500');
                    }

                    fetch(`/api/gis-data?tenant_id=${tenantId}&layer=${layerName}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.features && data.features.length > 0) {
                                const vectorSource = new ol.source.Vector({
                                    features: new ol.format.GeoJSON().readFeatures(data, { featureProjection: 'EPSG:3857' })
                                });
                                const vectorLayer = new ol.layer.Vector({
                                    source: vectorSource,
                                    style: layerConfigs[layerName].style,
                                    zIndex: layerConfigs[layerName].z,
                                    minZoom: layerConfigs[layerName].minZoom
                                });
                                map.addLayer(vectorLayer);
                                loadedLayers[layerName] = vectorLayer;
                            }
                        })
                        .catch(err => console.error(`Erro ao carregar ${layerName}:`, err))
                        .finally(() => {
                            if (textSpan) {
                                textSpan.innerHTML = originalText;
                                textSpan.classList.remove('animate-pulse', 'text-primary-500');
                            }
                        });
                };

                // EVENTO DAS CAMADAS NORMAIS
                document.querySelectorAll('.layer-toggle').forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        const layerName = this.getAttribute('data-layer');
                        if (this.checked) fetchAndDrawLayer(layerName, this);
                        else if (loadedLayers[layerName]) loadedLayers[layerName].setVisible(false);
                    });
                    if (checkbox.checked) fetchAndDrawLayer(checkbox.getAttribute('data-layer'), checkbox);
                });

                // EVENTO ESPECÍFICO PARA AS ZONAS
                document.querySelectorAll('.zona-toggle').forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        const layerName = 'zonas';
                        const sigla = this.getAttribute('data-zona-sigla');

                        // Atualiza o array de zonas ativas
                        if (this.checked) {
                            if (!zonasAtivas.includes(sigla)) zonasAtivas.push(sigla);
                        } else {
                            zonasAtivas = zonasAtivas.filter(s => s !== sigla);
                        }

                        // Se a camada principal de zonas ainda não foi baixada, baixa agora
                        if (!loadedLayers[layerName]) {
                            fetchAndDrawLayer(layerName, this);
                        } else {
                            // Se já baixou, apenas força o OpenLayers a redesenhar a tela aplicando as novas cores
                            loadedLayers[layerName].changed();
                            // Garante que a camada esteja visível
                            loadedLayers[layerName].setVisible(zonasAtivas.length > 0);
                        }
                    });
                });

                // ---------------------------------------------------------
                // 2. INTERFACE: BOTÃO DE CAMADAS E DRAG & DROP
                // ---------------------------------------------------------
                const panel = document.getElementById("layers-panel");
                const btnToggleLayers = document.getElementById("btn-toggle-layers");

                // Liga/Desliga a visibilidade da janela de camadas
                btnToggleLayers.addEventListener('click', () => {
                    panel.classList.toggle('hidden');
                });

                // Função de Arrastar (Drag)
                dragElement(panel);

                function dragElement(elmnt) {
                    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
                    const header = document.getElementById(elmnt.id + "-header");

                    header.onmousedown = dragMouseDown;

                    function dragMouseDown(e) {
                        e.preventDefault();
                        pos3 = e.clientX;
                        pos4 = e.clientY;
                        document.onmouseup = closeDragElement;
                        document.onmousemove = elementDrag;

                        // DESLIGA O BLUR PESADO PARA ARRASTAR LISO
                        elmnt.classList.add('dragging-now');
                    }

                    function elementDrag(e) {
                        e.preventDefault();
                        pos1 = pos3 - e.clientX;
                        pos2 = pos4 - e.clientY;
                        pos3 = e.clientX;
                        pos4 = e.clientY;

                        requestAnimationFrame(() => {
                            let newTop = elmnt.offsetTop - pos2;
                            let newLeft = elmnt.offsetLeft - pos1;

                            if (newTop < 10) newTop = 10;
                            if (newLeft < 10) newLeft = 10;
                            if (newTop > window.innerHeight - elmnt.clientHeight - 10)
                                newTop = window.innerHeight - elmnt.clientHeight - 10;
                            if (newLeft > window.innerWidth - elmnt.clientWidth - 10)
                                newLeft = window.innerWidth - elmnt.clientWidth - 10;

                            elmnt.style.top = newTop + "px";
                            elmnt.style.left = newLeft + "px";
                            elmnt.style.right = "auto";
                        });
                    }

                    function closeDragElement() {
                        document.onmouseup = null;
                        document.onmousemove = null;

                        // RELIGA O BLUR BONITO QUANDO SOLTAR
                        elmnt.classList.remove('dragging-now');
                    }
                }

                // 1. Lógica da Busca (COM ALERTA BONITO DO FILAMENT)
                const inputBusca = document.getElementById('input-busca-lote');
                // 1. Escuta o evento que o Dropdown Alpine vai disparar
                window.addEventListener('voar-para-lote', (e) => {
                    const data = e.detail;
                    if (data && data.coords) {
                        const targetCoords = ol.proj.fromLonLat([data.coords[0], data.coords[1]]);
                        view.animate({ center: targetCoords, zoom: 20, duration: 2000 });

                        // Opcional: Descomente a linha abaixo se quiser que a ficha lateral já abra automaticamente ao chegar no voo
                        // setTimeout(() => { Livewire.dispatch('abrirFichaImovel', { loteId: data.id, loteNome: data.label }); }, 2100);
                    }
                });

                // 2. Lógica de Clique com Filtro de Lote
                map.on('pointermove', function (e) {
                    const feature = map.forEachFeatureAtPixel(e.pixel, feature => feature);
                    // O cursor só vira mãozinha se o mouse passar por cima de um LOTE!
                    map.getTargetElement().style.cursor = (feature && feature.get('layer') === 'lotes') ? 'pointer' : '';
                });

                map.on('singleclick', function (evt) {
                    const feature = map.forEachFeatureAtPixel(evt.pixel, feature => feature);

                    if (feature) {
                        // Verifica com precisão se é um Lote (ignora bairros, logradouros, etc)
                        if (feature.get('layer') === 'lotes') {
                            const loteId = feature.get('id');
                            const loteNome = feature.get('name') || 'S/N';
                            if (loteId) {
                                Livewire.dispatch('abrirFichaImovel', { loteId: loteId, loteNome: loteNome });
                            }
                        }
                    }
                });

                // ---------------------------------------------------------
                // 4. FERRAMENTAS DE MEDIÇÃO (LINHA E ÁREA)
                // ---------------------------------------------------------
                const measureTooltipElement = document.getElementById('measure-tooltip');
                const measureOverlay = new ol.Overlay({
                    element: measureTooltipElement,
                    offset: [0, -15],
                    positioning: 'bottom-center'
                });
                map.addOverlay(measureOverlay);

                // Camada invisível para desenhar a linha/área por cima
                const drawSource = new ol.source.Vector();
                const drawLayer = new ol.layer.Vector({
                    source: drawSource,
                    style: new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#ef4444', width: 3, lineDash: [5, 5] }),
                        fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.2)' })
                    }),
                    zIndex: 9999
                });
                map.addLayer(drawLayer);

                let currentMeasureInteraction = null;
                let currentDrawInteraction = null;
                let currentSnapInteraction = null;
                let activeTool = 'pan'; // pan = mouse normal

                // ---------------------------------------------------------
                // 5. FERRAMENTA DE CRIAÇÃO (DESENHAR ARTEFATOS)
                // ---------------------------------------------------------
                let currentDrawEntity = null; // Guarda se estamos desenhando um lote, arvore, etc.
                const formatGeoJSON = new ol.format.GeoJSON({ featureProjection: 'EPSG:3857', dataProjection: 'EPSG:4326' });

                window.enableDrawing = function (entityType) {
                    // Limpa interações anteriores
                    if (currentMeasureInteraction) map.removeInteraction(currentMeasureInteraction);
                    if (currentDrawInteraction) map.removeInteraction(currentDrawInteraction);
                    if (currentSnapInteraction) map.removeInteraction(currentSnapInteraction);

                    drawSource.clear();
                    measureTooltipElement.style.display = 'none';

                    activeTool = 'draw';
                    currentDrawEntity = entityType;

                    let geometryType = 'Polygon';
                    if (['arvore', 'poste'].includes(entityType)) geometryType = 'Point';
                    if (entityType === 'logradouro') geometryType = 'LineString';

                    map.getTargetElement().style.cursor = 'crosshair';

                    // 1. PREPARA A FERRAMENTA DE DESENHO
                    currentDrawInteraction = new ol.interaction.Draw({
                        source: drawSource,
                        type: geometryType,
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({ color: '#3b82f6', width: 3, lineDash: [5, 5] }),
                            fill: new ol.style.Fill({ color: 'rgba(59, 130, 246, 0.2)' }),
                            image: new ol.style.Circle({ radius: 6, fill: new ol.style.Fill({ color: '#3b82f6' }) })
                        })
                    });

                    // 2. O QUE ACONTECE QUANDO TERMINAR DE DESENHAR?
                    currentDrawInteraction.on('drawend', function (e) {
                        const geoJson = formatGeoJSON.writeFeatureObject(e.feature);

                        setTimeout(() => drawSource.clear(), 500);
                        map.getTargetElement().style.cursor = '';

                        // Desliga as ferramentas
                        map.removeInteraction(currentDrawInteraction);
                        if (currentSnapInteraction) map.removeInteraction(currentSnapInteraction);

                        activeTool = 'pan';

                        Livewire.dispatch('abrirModalCriacao', { entityType: currentDrawEntity, geoJson: geoJson.geometry });
                    });

                    // 🚨 A MÁGICA ESTÁ NESTA ORDEM EXATA AQUI EMBAIXO: 🚨

                    // PASSO 1: Adiciona o Desenho no mapa PRIMEIRO
                    map.addInteraction(currentDrawInteraction);

                    // PASSO 2: Adiciona o Ímã (Snap) DEPOIS do Desenho
                    // Como é adicionado por último, ele fica no "topo" e domina o mouse!
                    if (['lote', 'edificacao'].includes(entityType) && loadedLayers['lotes']) {
                        currentSnapInteraction = new ol.interaction.Snap({
                            source: loadedLayers['lotes'].getSource(),
                            pixelTolerance: 20 // Distância que o ímã vai puxar o mouse (20 pixels)
                        });
                        map.addInteraction(currentSnapInteraction);
                    }
                };

                // ESCUTA O COMANDO DO LIVEWIRE PARA LIMPAR O RASCUNHO DA TELA
                window.addEventListener('limpar-rascunho-mapa', () => {
                    if (drawSource) drawSource.clear();
                });

                // NOVO: ESCUTA O COMANDO PARA INJETAR O LOTE DEFINITIVO NO MAPA (Sucesso)
                window.addEventListener('adicionar-lote-mapa', (e) => {
                    // O Livewire 3 às vezes empacota o detalhe em um array, isso garante que pegamos o objeto
                    const data = e.detail[0] || e.detail;

                    // 1. Apaga a linha de rascunho azul
                    if (drawSource) drawSource.clear();

                    // 2. Verifica se o checkbox da camada de lotes está ativado
                    const checkbox = document.querySelector('input[data-layer="lotes"]');
                    if (checkbox && checkbox.checked && loadedLayers['lotes']) {

                        // 3. Converte as coordenadas do banco (4326) para o visual do mapa (3857)
                        const feature = new ol.Feature({
                            geometry: new ol.format.GeoJSON().readGeometry(data.geo, {
                                dataProjection: 'EPSG:4326',
                                featureProjection: 'EPSG:3857'
                            }),
                            id: data.id,             // Fundamental para o clique na ficha funcionar!
                            name: data.numero_lote,  // Fundamental para mostrar o número no meio do lote!
                            layer: 'lotes'           // Fundamental para a mãozinha do mouse aparecer!
                        });

                        // 4. Injeta na camada verde e força o OpenLayers a desenhar
                        loadedLayers['lotes'].getSource().addFeature(feature);
                    }
                });

                // NOVO: APAGA O LOTE DA TELA IMEDIATAMENTE APÓS A EXCLUSÃO
                window.addEventListener('remover-lote-mapa', (e) => {
                    const data = e.detail[0] || e.detail;

                    if (loadedLayers['lotes']) {
                        const source = loadedLayers['lotes'].getSource();
                        // Procura no mapa a feature que tem o ID exato do lote excluído
                        const feature = source.getFeatures().find(f => f.get('id') == data.id);
                        if (feature) {
                            source.removeFeature(feature);
                        }
                    }
                });

                function enableMeasurement(type, buttonElement) {
                    // Limpa tudo
                    if (currentMeasureInteraction) map.removeInteraction(currentMeasureInteraction);
                    drawSource.clear();
                    measureTooltipElement.style.display = 'none';

                    // Reseta cores dos botões
                    document.getElementById('btn-measure-line').classList.remove('bg-primary-100', 'text-primary-600');
                    document.getElementById('btn-measure-area').classList.remove('bg-primary-100', 'text-primary-600');

                    // Se clicou na mesma ferramenta que já estava ativa, desliga ela.
                    if (activeTool === type) {
                        activeTool = 'pan';
                        map.getTargetElement().style.cursor = '';
                        return;
                    }

                    // Liga a ferramenta
                    activeTool = type;
                    buttonElement.classList.add('bg-primary-100', 'text-primary-600');
                    map.getTargetElement().style.cursor = 'crosshair';

                    currentMeasureInteraction = new ol.interaction.Draw({
                        source: drawSource,
                        type: type === 'line' ? 'LineString' : 'Polygon',
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({ color: '#fff', width: 2, lineDash: [5, 5] })
                        })
                    });

                    currentMeasureInteraction.on('drawstart', function () {
                        drawSource.clear();
                        measureTooltipElement.style.display = 'block';
                    });

                    currentMeasureInteraction.on('drawend', function (e) {
                        const geom = e.feature.getGeometry();
                        // Calcula o tamanho exato matematicamente
                        const output = type === 'line'
                            ? (ol.sphere.getLength(geom)).toFixed(2) + ' m'
                            : (ol.sphere.getArea(geom)).toFixed(2) + ' m²';

                        measureTooltipElement.innerHTML = output;

                        // Posiciona a caixinha preta
                        const position = type === 'line' ? geom.getLastCoordinate() : geom.getInteriorPoint().getCoordinates();
                        measureOverlay.setPosition(position);

                        // Volta para o modo normal após o término do desenho
                        activeTool = 'pan';
                        buttonElement.classList.remove('bg-primary-100', 'text-primary-600');
                        map.getTargetElement().style.cursor = '';
                        map.removeInteraction(currentMeasureInteraction);
                    });

                    map.addInteraction(currentMeasureInteraction);
                }

                const btnMeasureLine = document.getElementById('btn-measure-line');
                const btnMeasureArea = document.getElementById('btn-measure-area');

                if (btnMeasureLine) {
                    btnMeasureLine.addEventListener('click', function () { enableMeasurement('line', this); });
                }
                if (btnMeasureArea) {
                    btnMeasureArea.addEventListener('click', function () { enableMeasurement('area', this); });
                }

            });
        </script>

    </div>


    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loteSearch', () => ({
                termo: '',
                resultados: [],
                loading: false,
                dropdownStyle: '',
                dropdownMaxHeight: 300,

                posicionarDropdown() {
                    this.$nextTick(() => {
                        const input = this.$refs.inputField;
                        if (!input) return;
                        const rect = input.getBoundingClientRect();
                        const espacoDisponivel = window.innerHeight - rect.bottom - 16;
                        this.dropdownMaxHeight = Math.max(200, espacoDisponivel);
                        this.dropdownStyle = `top: ${rect.bottom + 8}px; left: ${rect.left}px; width: 480px;`; // ← largura aqui
                    });
                },

                buscar() {
                    if (this.termo.length < 2) { this.resultados = []; return; }
                    this.loading = true;
                    this.posicionarDropdown(); // ← NOVO
                    const tenantId = {{ $tenantId }};
                    fetch(`/api/search-lote?tenant_id=${tenantId}&termo=${this.termo}`)
                        .then(res => res.json())
                        .then(data => { this.resultados = data; })
                        .catch(() => { this.resultados = []; })
                        .finally(() => { this.loading = false; });
                },

                voarPara(res) {
                    this.resultados = [];
                    this.termo = '';
                    window.dispatchEvent(new CustomEvent('voar-para-lote', { detail: res }));
                }
            }));
        });
    </script>

    <x-filament-actions::modals />

</div>