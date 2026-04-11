<div class="relative w-screen h-screen overflow-hidden bg-gray-100 dark:bg-gray-900 font-sans text-gray-800">

    <div wire:ignore>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <link rel="stylesheet" href="{{ asset('css/gis/mapa-sigweb.css') }}">
       
        <script>
            window.mapConfig = {
                tenantId: {{ $tenantId }},
                tenantSlug: '{{ $tenantSlug }}',
                mapLat: {{ $mapLat }},
                mapLon: {{ $mapLon }},
                mapZoom: {{ $mapZoom }},
                isCidadao: true
            };
        </script>

        {{-- O MAPA E TOOLTIPS --}}
        <div id="sigweb-map" class="absolute inset-0 z-0 w-full h-full"></div>
        <div id="measure-tooltip" class="ol-tooltip" style="display: none;"></div>
        <div id="feature-tooltip"
            class="fixed bg-gray text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-xl pointer-events-none z-[9999] ol-tooltip-logradouro"
            style="display: none; transform: translate(-50%, -150%);"></div>

        {{-- BARRA SUPERIOR CENTRALIZADA (Cópia Fiel do seu App) --}}
        <div class="absolute top-4 left-0 w-full px-4 z-40 pointer-events-none flex items-start justify-between">

            <div class="pointer-events-auto">
                <a href="/cidadao"
                    class="bg-white dark:bg-gray-800/95 shadow-lg border border-gray-200 dark:border-gray-700 px-4 py-2.5 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition-all flex items-center gap-2">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                    <span class="hidden sm:inline">Painel</span>
                </a>
            </div>

            <div
                class="flex items-center gap-2 pointer-events-auto bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 rounded-2xl max-w-2xl w-full mx-4">

                {{-- Busca Integrada com AUTOCOMPLETE (Alpine.js original seu) --}}
                <div x-data="loteSearch()"
                    class="relative flex items-center flex-1 min-w-[200px] border-r border-gray-100 dark:border-gray-700 px-2"
                    x-ref="inputWrapper">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 mr-2" />
                    <input type="text" x-model="termo" @input.debounce.500ms="buscar(); posicionarDropdown()"
                        @keydown.enter="buscar()" x-ref="inputField"
                        placeholder="Buscar lote, Cód Tributário ou Logradouro..."
                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 dark:text-gray-200 outline-none">
                    <div x-show="loading" style="display: none;" class="absolute right-3">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-500"></div>
                    </div>
                    {{-- Dropdown de Resultados --}}
                    <div x-show="resultados.length > 0" style="display: none;" @click.outside="resultados = []"
                        x-ref="dropdown" :style="dropdownStyle"
                        class="fixed bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-[9999]">
                        <ul class="overflow-y-auto custom-scrollbar" :style="`max-height: ${dropdownMaxHeight}px`">
                            <template x-for="(res, index) in resultados" :key="index">
                                <li @click="voarPara(res)"
                                    class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer transition-colors flex items-start gap-3">
                                    <template x-if="res.tipo === 'lote'"><x-heroicon-o-map-pin
                                            class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" /></template>
                                    <template x-if="res.tipo === 'logradouro'"><x-heroicon-o-minus
                                            class="w-5 h-5 text-slate-500 flex-shrink-0 mt-0.5" /></template>
                                    <template x-if="res.tipo === 'bairro'"><x-heroicon-o-map
                                            class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" /></template>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-800 dark:text-gray-100"
                                            x-text="res.titulo"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium"
                                            x-text="res.subtitulo"></span>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                {{-- BOTÃO FILTRO AVANÇADO --}}
                <button type="button" x-data="{ ativo: @entangle('filtroAvancadoAtivo') }"
                    x-on:click="ativo ? $wire.limparFiltroAvancado() : $wire.mountAction('filtroAvancadoAction')"
                    :class="ativo ? 'bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-400' :
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                    class="relative p-2 rounded-lg transition-colors flex items-center justify-center"
                    :title="ativo ? 'Limpar Filtro' : 'Filtro Avançado'">
                    <x-heroicon-o-funnel class="w-5 h-5" />
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                <button id="btn-pan" title="Mover Mapa (Cancelar Ferramentas)"
                    class="p-2 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl transition-colors focus:outline-none">
                    <x-heroicon-o-hand-raised class="w-5 h-5" />
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                <button id="btn-measure-line" title="Medir Distância"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors focus:outline-none flex items-center gap-1">
                    <x-heroicon-o-arrows-right-left class="w-5 h-5" />
                </button>

                <button id="btn-measure-area" title="Medir Área"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors focus:outline-none flex items-center gap-1">
                    <x-heroicon-o-view-columns class="w-5 h-5" />
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                <button id="btn-satelite" title="Alternar Mapa Base"
                    class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-2 font-bold text-sm">
                    <x-heroicon-o-globe-americas class="w-5 h-5" />
                    <span id="satelite-text" class="hidden md:inline">Satélite</span>
                </button>

                <button id="btn-toggle-layers" title="Camadas do Mapa"
                    class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-primary-600 dark:text-primary-400 font-bold text-sm flex items-center gap-2">
                    <x-heroicon-o-square-3-stack-3d class="w-5 h-5" />
                    <span class="hidden md:inline">Camadas</span>
                </button>

            </div>
            <div class="w-10"></div>
        </div>

        {{-- JANELA DE CAMADAS ARRASTÁVEL (Seu HTML Original sem o PGV/Social) --}}
        <div id="layers-panel" style="top: 80px; left: calc(100vw - 340px);" x-data="{ activeTab: 'base' }"
            class="absolute bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-2xl rounded-2xl border-2 border-gray-200/50 dark:border-gray-700/50 flex flex-col overflow-hidden z-20 pointer-events-auto">
            <div class="bg-gray-100/50 dark:bg-gray-800/50 border-b border-gray-200/50 dark:border-gray-700/50 px-4 py-3 flex justify-between items-center cursor-grab active:cursor-grabbing"
                id="layers-panel-header">
                <h3
                    class="font-bold text-sm text-gray-700 dark:text-gray-200 flex items-center gap-2 uppercase tracking-wider">
                    <x-heroicon-o-bars-3 class="w-4 h-4 text-gray-500" /> Camadas do Mapa
                </h3>
                <button type="button" onmousedown="event.stopPropagation()"
                    onclick="document.getElementById('layers-panel').classList.add('hidden')"
                    class="p-1 -mr-1 rounded-lg text-gray-400 hover:text-red-500 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all cursor-pointer focus:outline-none">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
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
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="perimetros"
                                class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-red-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Perímetros/Limites</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="bairros"
                                class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-blue-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Bairros</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="loteamentos"
                                class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-blue-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Loteamentos</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="quadras"
                                class="layer-toggle rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-orange-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                </div><span class="layer-text truncate">Quadras</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="lotes"
                                class="layer-toggle rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                </div><span class="layer-text truncate">Lotes</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="logradouros"
                                class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div><span
                                    class="layer-text truncate">Logradouros</span>
                            </span></label>
                    </div>
                </div>

                {{-- GRUPO 2: ZONEAMENTO URBANO --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'zonas' ? '' : 'zonas'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Zoneamento Urbano</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'zonas' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'zonas'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full overflow-hidden">
                        @foreach ($zonasTipos as $zona)
                            @php $rgbLimpo = str_replace(['(', ')'], '', $zona['rgb']); @endphp
                            <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"
                                title="{{ $zona['name'] }}"><input type="checkbox" data-layer="zonas"
                                    data-zona-sigla="{{ $zona['sigla'] }}"
                                    class="zona-toggle rounded border-gray-400 shadow-sm w-4 h-4 flex-shrink-0"
                                    style="color: rgb({{ $rgbLimpo }});"><span
                                    class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-2">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10"
                                        style="background-color: rgb({{ $rgbLimpo }});"></div><span
                                        class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">{{ $zona['sigla'] }}
                                        - {{ $zona['name'] }}</span>
                                </span></label>
                        @endforeach
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
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="arvores"
                                class="layer-toggle rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full flex-shrink-0"></div><span
                                    class="layer-text truncate">Arborização Urbana</span>
                            </span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="postes"
                                class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div><span
                                    class="layer-text truncate">Iluminação Pública</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                            <input type="checkbox" data-layer="pontos_panoramicos"
                                class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div
                                    class="w-3 h-3 bg-blue-500 rounded-full flex-shrink-0 flex items-center justify-center">
                                    <x-heroicon-o-camera class="w-2 h-2 text-white" />
                                </div>
                                <span class="layer-text truncate">Imagens 360º</span>
                            </span>
                        </label>

                    </div>
                </div>

                {{-- GRUPO 4: CEMITÉRIOS --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'cemiterios' ? '' : 'cemiterios'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Gestão de Cemitérios</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'cemiterios' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'cemiterios'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="cemiterios"
                                class="layer-toggle rounded border-gray-300 text-purple-600 focus:ring-purple-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-purple-600 rounded-sm opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Cemitérios</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="quadras_cemiterio"
                                class="layer-toggle rounded border-gray-300 text-indigo-500 focus:ring-indigo-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-indigo-500 rounded-sm opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Quadras (Cemitério)</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="logradouros_cemiterio"
                                class="layer-toggle rounded border-gray-300 text-slate-500 focus:ring-slate-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-500 rounded-sm opacity-80 flex-shrink-0"></div><span
                                    class="layer-text truncate">Ruas (Cemitério)</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="jazigos"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Jazigos / Túmulos</span>
                            </span></label>
                    </div>
                </div>

                {{-- GRUPO 5: ZONA RURAL --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'rural' ? '' : 'rural'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Cadastro Rural</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'rural' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'rural'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="rural-localidades"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Localidades e Distritos</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="rural-propriedades"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Propriedades (INCRA/CAR)</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="rural-estradas"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Estradas e Vicinais</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="rural-hidrografias"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Rios, Lagos e Nascentes</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="rural-pontes"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Pontes Rurais</span>
                            </span></label>
                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="rural-pontos-interesse"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div><span
                                    class="layer-text truncate">Pontos de Interesse</span>
                            </span></label>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ⚡ FICHA DO IMÓVEL (GERENCIADA PELO LIVEWIRE) ⚡ --}}
    <div x-data="{ open: @entangle('showFicha') }" x-show="open"
        class="fixed inset-y-0 right-0 z-50 bg-white dark:bg-gray-800 shadow-2xl border-l border-gray-200 dark:border-gray-700 transform transition-transform duration-300 flex flex-col"
        x-transition:enter="translate-x-0" x-transition:leave="translate-x-0"
        style="display: none; width: 300px !important;">

        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <x-heroicon-o-map-pin class="w-5 h-5 text-primary-600" /> Detalhes do Lote
            </h2>
            <button wire:click="fecharFicha" @click="open = false"
                class="text-gray-400 hover:text-red-500 transition-colors">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto">
            @if ($loteAtivoId)
                <div
                    class="mb-6 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-xl border border-gray-200 dark:border-gray-600">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Lote / Inscrição</p>
                    <p class="text-xl font-black text-gray-800 dark:text-white">{{ $loteAtivoNome }}</p>
                    <p class="text-xs text-gray-500 mt-1">ID Sistema: #{{ $loteSequentialId }}</p>

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

                {{-- AÇÕES DO LOTE --}}
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase pt-6 mb-4">Ações do Lote</h3>
                <div class="space-y-3">
                    <button wire:click="mountAction('verUnidades')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Ver
                            Unidades Imobiliárias</span>
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>

                    <div class="flex items-center gap-2 w-full">
                        <button wire:click="toggleEdificacoesLote"
                            style="{{ $mostrarEdificacoesLoteAtivo ? 'background-color: #ecfdf5; border-color: #10b981;' : '' }}"
                            class="flex-1 flex items-center justify-between px-4 py-3 border rounded-xl transition-all group bg-white border-gray-200 hover:border-emerald-500 dark:bg-gray-800 dark:border-gray-700">
                            <span
                                class="text-sm font-medium flex items-center gap-2 {{ $mostrarEdificacoesLoteAtivo ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-200 group-hover:text-emerald-600' }}">
                                <x-heroicon-o-home class="w-4 h-4" />
                                {{ $mostrarEdificacoesLoteAtivo ? 'Ocultar' : 'Ver Edificações' }}
                            </span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <div class="w-9 h-5 rounded-full transition-colors"
                                    style="{{ $mostrarEdificacoesLoteAtivo ? 'background-color: #10b981;' : 'background-color: #e5e7eb;' }}">
                                </div>
                                <div class="absolute left-[2px] top-[2px] bg-white border border-gray-300 rounded-full h-4 w-4 transition-transform"
                                    style="{{ $mostrarEdificacoesLoteAtivo ? 'transform: translateX(100%); border-color: white;' : '' }}">
                                </div>
                            </div>
                        </button>

                    </div>

                    {{-- BOTÃO DE VIABILIDADE --}}
                    <button wire:click="mountAction('consultarViabilidadeAction')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">
                            Consulta de Viabilidade
                        </span>
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                    {{-- BOTÃO DE MEMORIAL DESCRITIVO --}}
                    <button wire:click="mountAction('gerarMemorialAction')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-emerald-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-emerald-600">
                            Memorial Descritivo
                        </span>
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                    {{-- BOTÃO DE CROQUI DE LOCALIZAÇÃO --}}
                    <button wire:click="mountAction('exportarCroqui')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-blue-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-blue-600">
                            Croqui de Localização
                        </span>
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                    {{-- BOTÃO DO STREET VIEW --}}
                    <button wire:click="mountAction('abrirStreetViewAction')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-blue-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-blue-600">
                            Explorar Street View
                        </span>
                        <x-heroicon-o-globe-americas class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                </div>
            @endif
        </div>

    </div>

    {{-- SCRIPTS DO MOTOR E BUSCA --}}
    <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
    <script src="{{ asset('js/gis/mapa-cidadao-engine.js') }}"></script>

    {{-- Carrega o Google Maps (Obrigatório para o Street View) --}}
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=geometry" async
        defer></script>

    <script>
        /* IMPRIMIR CONSULTA DE VIABILIDADE DO LOTE (COM DESTAQUE) */
        window.capturarMapaEImprimir = function(loteId, cnaes) { // 🛑 Nome restaurado e parâmetro cnaes de volta!

            // 🛑 MÁGICA 1: Encontrar a feature do lote e aplicar o "marca-texto" antes da foto!
            let featureToHighlight = null;
            if (window.loadedLayers && window.loadedLayers['lotes']) {
                const source = window.loadedLayers['lotes'].getSource();
                featureToHighlight = source.getFeatures().find(f => f.get('id') == loteId);

                if (featureToHighlight) {
                    featureToHighlight.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff0000',
                            width: 4
                        }), // Borda vermelha chamativa
                        fill: new ol.style.Fill({
                            color: 'rgba(250, 204, 21, 0.5)'
                        }) // Fundo amarelo translúcido
                    }));
                }
            }

            // Aumentamos o delay para 800ms para garantir a renderização do destaque antes do print
            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        // 🛑 CORREÇÃO 1: Mede a div HTML diretamente, sem depender da variável 'map' interna
                        const mapaElement = document.getElementById('sigweb-map');
                        mapCanvas.width = mapaElement.clientWidth;
                        mapCanvas.height = mapaElement.clientHeight;
                        const mapContext = mapCanvas.getContext('2d');

                        // 🛑 CORREÇÃO 2: Pinta o fundo de branco OBRIGATORIAMENTE para JPEGs
                        mapContext.fillStyle = '#ffffff';
                        mapContext.fillRect(0, 0, mapCanvas.width, mapCanvas.height);

                        Array.prototype.forEach.call(canvases, function(canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);

                                // Limpa a matriz antiga antes de aplicar a nova
                                mapContext.setTransform(1, 0, 0, 1, 0, 0);

                                const transform = canvas.style.transform;
                                if (transform) {
                                    const matrix = transform.match(/^matrix\(([^\(]*)\)$/);
                                    if (matrix) {
                                        const m = matrix[1].split(',').map(Number);
                                        mapContext.setTransform(m[0], m[1], m[2], m[3], m[4], m[5]);
                                    }
                                }
                                mapContext.drawImage(canvas, 0, 0);
                            }
                        });

                        // 🛑 CORREÇÃO 3: Devolve tudo ao normal no final para não bugar outras leituras
                        mapContext.globalAlpha = 1;
                        mapContext.setTransform(1, 0, 0, 1, 0, 0);

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);

                        // 🛑 Manda para o Livewire chamando imprimirViabilidade na ordem certa!
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirViabilidade(dataURL, cnaes, loteId);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para a Consulta:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    // 🛑 MÁGICA 2: "Limpar a tinta". Devolve a cor original ao lote!
                    if (featureToHighlight) {
                        featureToHighlight.setStyle(undefined);
                    }
                }
            }, 800);
        };

        /* IMPRIMIR CROQUI DE LOCALIZAÇÃO DO LOTE (COM DESTAQUE) */
        window.capturarMapaEImprimirCroqui = function(loteId) {

            // 🛑 MÁGICA 1: Encontrar a feature do lote e aplicar um "marca-texto" antes da foto!
            let featureToHighlight = null;
            if (window.loadedLayers && window.loadedLayers['lotes']) {
                const source = window.loadedLayers['lotes'].getSource();
                featureToHighlight = source.getFeatures().find(f => f.get('id') == loteId);

                if (featureToHighlight) {
                    featureToHighlight.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff0000',
                            width: 4
                        }), // Borda vermelha chamativa
                        fill: new ol.style.Fill({
                            color: 'rgba(250, 204, 21, 0.5)'
                        }) // Fundo amarelo translúcido
                    }));
                }
            }

            // Aumentamos o delay para 800ms para dar tempo da placa de vídeo renderizar o amarelo antes do print
            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        // 🛑 CORREÇÃO 1: Mede a div HTML diretamente, sem depender da variável 'map' interna
                        const mapaElement = document.getElementById('sigweb-map');
                        mapCanvas.width = mapaElement.clientWidth;
                        mapCanvas.height = mapaElement.clientHeight;
                        const mapContext = mapCanvas.getContext('2d');

                        // 🛑 CORREÇÃO 2: Pinta o fundo de branco OBRIGATORIAMENTE para JPEGs
                        mapContext.fillStyle = '#ffffff';
                        mapContext.fillRect(0, 0, mapCanvas.width, mapCanvas.height);

                        Array.prototype.forEach.call(canvases, function(canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);

                                // Limpa a matriz antiga antes de aplicar a nova
                                mapContext.setTransform(1, 0, 0, 1, 0, 0);

                                const transform = canvas.style.transform;
                                if (transform) {
                                    const matrix = transform.match(/^matrix\(([^\(]*)\)$/);
                                    if (matrix) {
                                        const m = matrix[1].split(',').map(Number);
                                        mapContext.setTransform(m[0], m[1], m[2], m[3], m[4], m[5]);
                                    }
                                }
                                mapContext.drawImage(canvas, 0, 0);
                            }
                        });

                        // 🛑 CORREÇÃO 3: Devolve tudo ao normal no final para não bugar outras leituras
                        mapContext.globalAlpha = 1;
                        mapContext.setTransform(1, 0, 0, 1, 0, 0);

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);

                        // Manda para o Livewire
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirCroqui(loteId, dataURL);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para o Croqui:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    // 🛑 MÁGICA 2: "Limpar a tinta". Devolve a cor original ao lote!
                    if (featureToHighlight) {
                        featureToHighlight.setStyle(undefined);
                    }
                }
            }, 800);
        };
    </script>


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
                        this.dropdownMaxHeight = Math.max(200, window.innerHeight - rect
                            .bottom - 16);
                        this.dropdownStyle =
                            `top: ${rect.bottom + 8}px; left: ${rect.left}px; width: 480px;`;
                    });
                },
                buscar() {
                    if (this.termo.length < 2) {
                        this.resultados = [];
                        return;
                    }
                    this.loading = true;
                    this.posicionarDropdown();
                    fetch(`/api/search-lote?tenant_id=${window.mapConfig.tenantId}&termo=${this.termo}`)
                        .then(res => res.json()).then(data => {
                            this.resultados = data;
                        })
                        .catch(() => {
                            this.resultados = [];
                        }).finally(() => {
                            this.loading = false;
                        });
                },
                voarPara(res) {
                    this.resultados = [];
                    this.termo = '';
                    window.dispatchEvent(new CustomEvent('voar-para-lote', {
                        detail: res
                    }));
                }
            }));
        });

        // Habilita arrasto livre da janela de camadas
        function dragElement(elmnt) {
            let pos1 = 0,
                pos2 = 0,
                pos3 = 0,
                pos4 = 0;
            if (document.getElementById(elmnt.id + "-header")) document.getElementById(elmnt.id + "-header").onmousedown =
                dragMouseDown;
            else elmnt.onmousedown = dragMouseDown;

            function dragMouseDown(e) {
                e.preventDefault();
                pos3 = e.clientX;
                pos4 = e.clientY;
                document.onmouseup = closeDragElement;
                document.onmousemove = elementDrag;
            }

            function elementDrag(e) {
                e.preventDefault();
                pos1 = pos3 - e.clientX;
                pos2 = pos4 - e.clientY;
                pos3 = e.clientX;
                pos4 = e.clientY;
                elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
                elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
            }

            function closeDragElement() {
                document.onmouseup = null;
                document.onmousemove = null;
            }
        }
        dragElement(document.getElementById("layers-panel"));
    </script>

    <x-filament-actions::modals />
</div>
