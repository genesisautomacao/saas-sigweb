<div class="relative w-screen h-screen overflow-hidden bg-gray-100 dark:bg-gray-900 font-sans text-gray-800">

    <div wire:ignore>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <link rel="stylesheet" href="{{ asset('css/gis/mapa-sigweb.css') }}">

        {{-- Lib para geração de PDF (TR Tangará Internet #16/#17 — A4 e A3) --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

        {{-- OVERLAY DE CARREGAMENTO PARA IMPRESSÃO --}}
        <div id="print-loading-overlay"
            style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 10000; flex-direction: column; align-items: center; justify-content: center; color: white;">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-white mb-4"></div>
            <h2 id="print-status-text" class="text-xl font-bold">Gerando PDF do mapa...</h2>
            <p class="text-sm opacity-80">Isso pode levar alguns segundos.</p>
        </div>

        <script>
            window.mapConfig = {
                tenantId: {{ $tenantId }},
                tenantSlug: '{{ $tenantSlug }}',
                mapLat: {{ $mapLat }},
                mapLon: {{ $mapLon }},
                mapZoom: {{ $mapZoom }},
                isCidadao: true,
                // D8 (2026-09-05): módulos ativos + rótulos das listas da mobilidade (ficha só leitura)
                modulos: @json($modulos),
                ortofotos: @json($ortofotos),
                mobRotulos: {
                    poi: @json(\App\Models\MobPontoInteresse::CATEGORIAS),
                    eixo: @json(\App\Models\MobEixo::TIPOS),
                    zona: @json(\App\Models\MobZona::TIPOS),
                },
            };
        </script>

        {{-- O MAPA E TOOLTIPS --}}
        <div id="sigweb-map" class="absolute inset-0 z-0 w-full h-full"></div>

        {{-- PoC AC item 8 — escala atual dinâmica (espelho do quadrinho da intranet) --}}
        <div id="coord-escala-atual"
            style="position:absolute;bottom:8px;left:16px;z-index:30;
                   background:rgba(17,24,39,.75);color:#f9fafb;
                   font:700 11px monospace;padding:4px 10px;border-radius:8px;
                   pointer-events:none;border:1px solid rgba(255,255,255,.1);">
            Escala 1: —</div>

        <div id="measure-tooltip" class="ol-tooltip" style="display: none;"></div>
        <div id="feature-tooltip"
            class="fixed bg-gray text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-xl pointer-events-none z-[9999] ol-tooltip-logradouro"
            style="display: none; transform: translate(-50%, -150%);"></div>

        {{-- BARRA SUPERIOR CENTRALIZADA (Cópia Fiel do seu App) --}}
        <div class="absolute top-4 left-0 w-full px-4 z-40 pointer-events-none flex items-start justify-between">

            {{-- ══ Onda 7 (UI) — mesmo estilo "glass"/painel lateral do mapa da intranet ══ --}}
            <style>
                .gis-topbar {
                    background: rgba(255, 255, 255, .88) !important;
                    -webkit-backdrop-filter: blur(16px) saturate(1.5);
                    backdrop-filter: blur(16px) saturate(1.5);
                    border: 1px solid rgba(255, 255, 255, .6) !important;
                    box-shadow: 0 12px 40px -12px rgba(15, 23, 42, .35) !important;
                    border-radius: 16px !important;
                }

                .dark .gis-topbar {
                    background: rgba(17, 24, 39, .82) !important;
                    border-color: rgba(255, 255, 255, .08) !important;
                }

                #layers-panel {
                    position: fixed !important;
                    top: 76px !important;
                    right: 0 !important;
                    left: auto !important;
                    bottom: 0 !important;
                    width: 330px !important;
                    max-width: 88vw;
                    border-radius: 16px 0 0 0 !important;
                    border: none !important;
                    border-left: 1px solid rgba(120, 120, 140, .16) !important;
                    border-top: 1px solid rgba(120, 120, 140, .16) !important;
                    box-shadow: -16px 0 44px -20px rgba(0, 0, 0, .35) !important;
                    z-index: 35 !important;
                    background: rgba(255, 255, 255, .94) !important;
                    -webkit-backdrop-filter: blur(14px);
                    backdrop-filter: blur(14px);
                }

                .dark #layers-panel {
                    background: rgba(17, 24, 39, .92) !important;
                }

                #layers-panel>.overflow-y-auto {
                    max-height: none !important;
                    flex: 1 1 auto;
                    padding-bottom: 28px;
                }

                #layers-panel-header {
                    cursor: default !important;
                    padding-top: 13px !important;
                    padding-bottom: 13px !important;
                }
            </style>

            <div class="pointer-events-auto">
                <a href="/cidadao"
                    class="gis-topbar px-4 py-2.5 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition-all flex items-center gap-2">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                    <span class="hidden sm:inline">Painel</span>
                </a>
            </div>

            <div
                class="gis-topbar flex items-center gap-2 pointer-events-auto p-1.5 rounded-2xl max-w-2xl w-full mx-4"
                style="max-width: 56rem;">
                {{-- max-width inline: os botões de zoom (itens 24/25) estouravam os 672px
                     do max-w-2xl e o flex espremia a BUSCA até sumir (2026-08-06) --}}

                {{-- Busca Integrada com AUTOCOMPLETE (Alpine.js original seu) --}}
                <div x-data="loteSearch()"
                    class="relative flex items-center flex-1 min-w-[200px] border-r border-gray-100 dark:border-gray-700 px-2"
                    style="flex: 1 1 220px; min-width: 200px;"
                    x-ref="inputWrapper">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 mr-2" />
                    <input type="text" x-model="termo" @input.debounce.500ms="buscar(); posicionarDropdown()"
                        @keydown.enter="buscar()" x-ref="inputField" placeholder="Pesquisar..."
                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 dark:text-gray-200 outline-none">
                    <div x-show="loading" style="display: none;" class="absolute right-3">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-500"></div>
                    </div>
                    {{-- Dropdown de Resultados — ancorado no campo, como um seletor
                         (o "fixed" antigo quebrou com o backdrop-filter da barra) --}}
                    <div x-show="resultados.length > 0" style="display: none; top: calc(100% + 10px); left: 0; width: 100%; min-width: 320px;"
                        @click.outside="resultados = []" x-ref="dropdown"
                        class="absolute bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-[9999]">
                        <ul class="overflow-y-auto custom-scrollbar" style="max-height: 55vh;">
                            <template x-for="(res, index) in resultados" :key="index">
                                <li @click="voarPara(res)"
                                    class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer transition-colors flex items-start gap-3">
                                    <template x-if="res.tipo === 'lote'"><x-heroicon-o-map-pin
                                            class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" /></template>
                                    <template x-if="res.tipo === 'edificio'"><x-heroicon-o-building-office-2
                                            class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" /></template>
                                    <template x-if="res.tipo === 'logradouro'"><x-heroicon-o-minus
                                            class="w-5 h-5 text-slate-500 flex-shrink-0 mt-0.5" /></template>
                                    <template x-if="res.tipo === 'bairro'"><x-heroicon-o-map
                                            class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" /></template>

                                    <template x-if="res.tipo === 'setor'"><x-heroicon-o-rectangle-group
                                            class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" /></template>
                                    <template x-if="res.tipo === 'distrito'"><x-heroicon-o-globe-americas
                                            class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" /></template>

                                    <template x-if="res.tipo === 'loteamento'">
                                        <x-heroicon-o-squares-2x2
                                            class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                                    </template>
                                    <template x-if="res.tipo === 'quadra'">
                                        <x-heroicon-o-stop class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" />
                                    </template>

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
                <button type="button" x-data="{ ativo: @entangle('filtroAvancadoAtivo') }" x-on:click="$wire.mountAction('filtroAvancadoAction')"
                    :class="ativo ? 'bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-400' :
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                    class="relative p-2 rounded-lg transition-colors flex items-center justify-center"
                    title="Filtro Avançado / Tematização">
                    <x-heroicon-o-funnel class="w-5 h-5" />
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                {{-- ZOOM IN/OUT (itens 24/25 do TR Internet) — no celular ficam ocultos
                     (pinça/scroll cobrem) para a busca nunca ser espremida --}}
                <button onclick="window.zoomMais()" title="Aproximar (Zoom In)"
                    class="btn-zoom-passo p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors">
                    <x-heroicon-o-magnifying-glass-plus class="w-5 h-5" />
                </button>
                <button onclick="window.zoomMenos()" title="Afastar (Zoom Out)"
                    class="btn-zoom-passo p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors">
                    <x-heroicon-o-magnifying-glass-minus class="w-5 h-5" />
                </button>
                <style>
                    /* Telas estreitas: esconde o zoom por botão para preservar a busca */
                    @media (max-width: 768px) {
                        .btn-zoom-passo { display: none !important; }
                    }
                </style>

                {{-- ZOOM EXTENSÃO + VISÃO ANTERIOR --}}
                <button onclick="window.zoomExtensao()" title="Visão Geral (Zoom Extensão)"
                    class="p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors">
                    <x-heroicon-o-home class="w-5 h-5" />
                </button>
                <button onclick="window.visaoAnterior()" title="Visão Anterior"
                    class="p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors">
                    <x-heroicon-o-arrow-uturn-left class="w-5 h-5" />
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

                {{-- DROPDOWN DE IMPRESSÃO PDF (TR Tangará Internet #16 e #17 — A4 e A3) --}}
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button @click="open = !open" type="button" title="Imprimir mapa em PDF"
                        class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-1 text-sm">
                        <x-heroicon-o-printer class="w-5 h-5" />
                        <span class="hidden md:inline font-bold">PDF</span>
                        <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform"
                            x-bind:class="open ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="open" style="display: none; background-color: white; border: 1px solid #e5e7eb;"
                        x-transition
                        class="absolute right-0 mt-2 w-64 dark:bg-gray-800 rounded-xl shadow-2xl z-[1001] overflow-hidden">
                        <div class="p-2">
                            <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                Formato de Impressão
                            </div>
                            @foreach (['A4', 'A3'] as $formato)
                                <div class="flex items-center gap-1 px-2 mb-1">
                                    <button
                                        @click="$dispatch('gerar-pdf-mapa', { size: '{{ $formato }}', orientation: 'portrait' }); open = false"
                                        style="text-align: left; background: none; border: none; padding: 6px 12px; font-size: 12px; color: #374151; flex: 1; cursor: pointer;"
                                        class="hover:bg-blue-50 rounded-lg">
                                        {{ $formato }} — Retrato
                                    </button>
                                    <button
                                        @click="$dispatch('gerar-pdf-mapa', { size: '{{ $formato }}', orientation: 'landscape' }); open = false"
                                        style="background-color: #eff6ff; color: #1d4ed8; font-size: 10px; font-weight: bold; border: none; padding: 4px 8px; border-radius: 6px; cursor: pointer;">
                                        PAISAGEM
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                {{-- DROPDOWN DE MAPAS BASE — espelho da intranet (2026-09-05): OSM, satélite Esri e as
                     ortofotos ATIVAS cadastradas no /admin (uma opção por ortofoto). Azure Maps fica fora
                     do público (a chave iria no HTML de qualquer visitante). Cores/fundos em style inline:
                     o CSS do painel cidadão não tem as classes de cor do Tailwind. --}}
                <div class="relative" x-data="{ open: false, activeBasemap: 'osm' }" @click.away="open = false"
                    @sync-basemap-ui.window="activeBasemap = $event.detail">
                    <button @click="open = !open" type="button" title="Alternar Mapa Base"
                        class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-2 font-bold text-sm">
                        <x-heroicon-o-globe-americas class="w-5 h-5" />
                        <span class="hidden md:inline">Mapas Base</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform"
                            x-bind:class="open ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open" x-transition
                        style="display: none; width: 250px; background-color: white; border: 1px solid #e5e7eb;"
                        class="absolute left-0 mt-2 dark:bg-gray-800 rounded-xl shadow-2xl z-[1001] overflow-hidden">
                        <div class="p-2">
                            <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mapas de Ruas</div>

                            <button type="button"
                                @click="activeBasemap = 'osm'; $dispatch('switch-basemap', 'osm'); open = false"
                                style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; border:none; background:none; border-radius:8px; font-size:13px; text-align:left; cursor:pointer;"
                                x-bind:style="activeBasemap === 'osm' ? 'color:#1d4ed8; font-weight:700; background:#eff6ff;' : 'color:#374151;'">
                                <span class="flex items-center gap-2"><x-heroicon-o-map class="w-4 h-4" /> OpenStreetMap</span>
                                <x-heroicon-o-check x-show="activeBasemap === 'osm'" class="w-4 h-4" />
                            </button>

                            <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2"
                                style="border-top:1px solid #f3f4f6;">Imagens Aéreas</div>

                            <button type="button"
                                @click="activeBasemap = 'esri_sat'; $dispatch('switch-basemap', 'esri_sat'); open = false"
                                style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; border:none; background:none; border-radius:8px; font-size:13px; text-align:left; cursor:pointer;"
                                x-bind:style="activeBasemap === 'esri_sat' ? 'color:#1d4ed8; font-weight:700; background:#eff6ff;' : 'color:#374151;'">
                                <span class="flex items-center gap-2"><x-heroicon-o-globe-asia-australia class="w-4 h-4" /> Esri World Imagery</span>
                                <x-heroicon-o-check x-show="activeBasemap === 'esri_sat'" class="w-4 h-4" />
                            </button>

                            {{-- Ortofotos CADASTRADAS da prefeitura (tabela `ortofotos`): sem cadastro = sem opção --}}
                            @foreach ($ortofotos as $orto)
                                <button type="button"
                                    @click="activeBasemap = 'ortofoto_{{ $orto['id'] }}'; $dispatch('switch-basemap', 'ortofoto_{{ $orto['id'] }}'); open = false"
                                    style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; border:none; background:none; border-radius:8px; font-size:13px; text-align:left; cursor:pointer;"
                                    x-bind:style="activeBasemap === 'ortofoto_{{ $orto['id'] }}' ? 'color:#1d4ed8; font-weight:700; background:#eff6ff;' : 'color:#374151;'">
                                    <span class="flex items-center gap-2"><x-heroicon-o-sparkles class="w-4 h-4" /> {{ $orto['nome'] }}</span>
                                    <x-heroicon-o-check x-show="activeBasemap === 'ortofoto_{{ $orto['id'] }}'" class="w-4 h-4" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

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

                {{-- D8 (2026-09-05, docs/Modulos_Permissoes.txt §9): os acordeons do mapa PÚBLICO seguem
                     a MESMA ordem e os MESMOS nomes da intranet, só com o que o cidadão já enxerga, e
                     cada um só aparece se o módulo estiver ativo na prefeitura ($modulos =
                     Modulos::ativos, calculado no mount). Mobilidade Urbana entra SÓ LEITURA. --}}

                @if (in_array('base_cartografica', $modulos, true))
                {{-- GRUPO: BASE CARTOGRÁFICA (abre por padrão — activeTab inicial = base) --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'base' ? '' : 'base'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Base Cartográfica</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'base' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'base'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">

                        {{-- Distritos --}}
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="perimetros"
                                class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-red-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Distritos / Limites</span>
                            </span></label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full"><input type="checkbox"
                                data-layer="bairros"
                                class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-blue-500 rounded-full opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Bairros</span>
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
                @endif

                @if (in_array('imobiliario', $modulos, true))
                {{-- GRUPO: CADASTRO IMOBILIÁRIO (módulo imobiliario) --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'imobiliario' ? '' : 'imobiliario'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Cadastro Imobiliário</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'imobiliario' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'imobiliario'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">

                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
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
                    </div>
                </div>

                {{-- GRUPO: ZONEAMENTO URBANO (módulo imobiliario) --}}
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
                @endif

                @if (in_array('imageamento', $modulos, true))
                {{-- GRUPO: IMAGEAMENTO (pontos panorâmicos 360 — saiu de Infraestrutura, D8) --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'imageamento' ? '' : 'imageamento'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Imageamento</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'imageamento' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'imageamento'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">
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
                @endif

                @if ($temMobilidade)
                {{-- GRUPO: MOBILIDADE URBANA — SÓ LEITURA (D8): as 8 camadas do módulo mob_infra com os
                     mesmos símbolos da intranet (setas de sentido nas vias, cor por destino nos fluxos).
                     Sem criação/edição, caneta de sentido, "Colorir por" ou coroplético. Clique = ficha
                     só leitura (engine); câmera abre o player público. Cores em style inline (o CSS do
                     painel cidadão não tem as classes de cor do Tailwind). --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'mobilidade' ? '' : 'mobilidade'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Mobilidade Urbana</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'mobilidade' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'mobilidade'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full overflow-hidden">

                        {{-- TRECHOS VIÁRIOS (levantamento) --}}
                        <div class="mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_trechos"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#0ea5e9;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Trechos Viários</span>
                                </span>
                            </label>
                            {{-- "Colorir por" em tempo real (mesma lista da intranet: colunas + kit) — só visual --}}
                            <div style="margin:6px 0 0 28px;">
                                <select id="mob-trecho-tema-select"
                                    onchange="window.dispatchEvent(new CustomEvent('sigweb-mob-trecho-tema', { detail: { tema: this.value || null } }))"
                                    style="width:100%; font-size:12px; border:1px solid #d1d5db; border-radius:8px; padding:5px 8px; background:#fff; color:#374151;">
                                    <option value="">Colorir por... (cor única)</option>
                                    @foreach ($mobTrechoTemas as $slug => $label)
                                        <option value="{{ $slug }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div id="mob-trecho-legenda" style="margin-top:4px; line-height:1.3; color:#374151;"></div>
                            </div>
                            <p style="font-size:10px; color:#9ca3af; margin:4px 0 0 28px; line-height:1.4;">Tiques = direção em que o levantamento foi feito. O sentido de tráfego está nas Vias Urbanas.</p>
                        </div>

                        {{-- VIAS URBANAS (sentido) + simulador + câmeras --}}
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/40">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_vias"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#2563eb;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Vias Urbanas (sentido)</span>
                                </span>
                            </label>
                            <div style="margin:4px 0 0 28px;">
                                <p style="font-size:10px; color:#9ca3af; line-height:1.4; margin:0;">
                                    <span style="color:#2563eb; font-weight:700;">&#9644;</span> mão única (setas = fluxo) ·
                                    <span style="color:#dc2626; font-weight:700;">&#9644;</span> mão dupla ·
                                    <span style="color:#9ca3af; font-weight:700;">&#9476;</span> sem classificação
                                </p>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563; margin-top:4px;"
                                    title="Anima as setas das vias no sentido do fluxo. Só visual.">
                                    <input type="checkbox" id="mob-fluxo-simular" class="rounded border-gray-300 w-3.5 h-3.5"
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-mob-fluxo-simular', { detail: { ligado: this.checked } }))">
                                    &#9654; Simular fluxo (animar setas)
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563; margin-top:4px;"
                                    title="Câmeras de monitoramento da cidade. Clique no ícone da câmera para assistir ao vivo.">
                                    <input type="checkbox" data-layer="mob_cameras" class="layer-toggle rounded border-gray-300 w-3.5 h-3.5">
                                    <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                        <span class="layer-text truncate">&#127909; Monitoramento em tempo real</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- SINALIZAÇÃO + filtro vertical/horizontal --}}
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/40">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_sinalizacoes"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#dc2626;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Sinalização Viária</span>
                                </span>
                            </label>
                            <div style="display:flex; gap:16px; margin:4px 0 0 28px;">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563;">
                                    <input type="checkbox" class="mob-sub-toggle rounded border-gray-300 w-3.5 h-3.5"
                                        data-mob-layer="mob_sinalizacoes" data-valor="vertical"> Vertical &#9679;
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563;">
                                    <input type="checkbox" class="mob-sub-toggle rounded border-gray-300 w-3.5 h-3.5"
                                        data-mob-layer="mob_sinalizacoes" data-valor="horizontal"> Horizontal &#9670;
                                </label>
                            </div>
                        </div>

                        {{-- PONTOS DE INTERESSE + filtro por categoria --}}
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/40">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_pontos_interesse"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#d97706;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Pontos de Interesse</span>
                                </span>
                            </label>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 8px; margin:4px 0 0 28px;">
                                @foreach (\App\Models\MobPontoInteresse::CATEGORIAS as $catValor => $catLabel)
                                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563; min-width:0;">
                                        <input type="checkbox" class="mob-sub-toggle rounded border-gray-300 w-3.5 h-3.5 flex-shrink-0"
                                            data-mob-layer="mob_pontos_interesse" data-valor="{{ $catValor }}">
                                        <span class="truncate">{{ $catLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- EIXOS + filtro por tipo --}}
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/40">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_eixos"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#16a34a;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Eixos (Ciclo / Carga / Rodovia)</span>
                                </span>
                            </label>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 8px; margin:4px 0 0 28px;">
                                @foreach (\App\Models\MobEixo::TIPOS as $tipoValor => $tipoLabel)
                                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563; min-width:0;">
                                        <input type="checkbox" class="mob-sub-toggle rounded border-gray-300 w-3.5 h-3.5 flex-shrink-0"
                                            data-mob-layer="mob_eixos" data-valor="{{ $tipoValor }}">
                                        <span class="truncate">{{ $tipoLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- ZONAS DE ESTUDO + filtro por tipo (sem coroplético no público) --}}
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/40">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_zonas"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#2563eb;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Zonas de Estudo (O/D, IBGE)</span>
                                </span>
                            </label>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 8px; margin:4px 0 0 28px;">
                                @foreach (\App\Models\MobZona::TIPOS as $tipoValor => $tipoLabel)
                                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563; min-width:0;">
                                        <input type="checkbox" class="mob-sub-toggle rounded border-gray-300 w-3.5 h-3.5 flex-shrink-0"
                                            data-mob-layer="mob_zonas" data-valor="{{ $tipoValor }}">
                                        <span class="truncate">{{ $tipoLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- FLUXOS O/D: cor por destino, rótulo = % do total (só percentuais no mapa) --}}
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/40">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="mob_fluxos"
                                    class="layer-toggle rounded border-gray-300 w-4 h-4 flex-shrink-0" style="color:#0891b2;">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0" style="margin-left:10px;">
                                    <span class="layer-text truncate">Fluxos O/D (linhas de desejo)</span>
                                </span>
                            </label>
                            @if (($mobFluxoDistribuicao['total'] ?? 0) > 0)
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 8px; margin:4px 0 0 28px;">
                                    @foreach ($mobFluxoDistribuicao['destinos'] as $destinoValor => $d)
                                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#4b5563; min-width:0;"
                                            title="Destino {{ $d['label'] }}: {{ number_format($d['percentual'], 1, ',', '.') }}% do total de deslocamentos">
                                            <input type="checkbox" class="mob-sub-toggle rounded border-gray-300 w-3.5 h-3.5 flex-shrink-0"
                                                data-mob-layer="mob_fluxos" data-valor="{{ $destinoValor }}">
                                            <span style="width:10px; height:10px; border-radius:2px; background:{{ $d['cor'] }}; display:inline-block; flex-shrink:0;"></span>
                                            <span class="truncate">{{ $d['label'] }} <span style="color:#9ca3af;">{{ number_format($d['percentual'], 1, ',', '.') }}%</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <p style="font-size:10px; color:#9ca3af; margin:4px 0 0 28px; line-height:1.4;">
                                Rótulo = % do total de deslocamentos · espessura proporcional ao fluxo · clique = ficha.
                                @if (($mobFluxoDistribuicao['intrazonal'] ?? 0) > 0)
                                    <br>{{ number_format($mobFluxoDistribuicao['intrazonal_percentual'], 1, ',', '.') }}% são deslocamentos dentro da própria zona — não viram linha no mapa.
                                @endif
                            </p>
                        </div>

                    </div>
                </div>
                @endif

                @php $temInfraPublica = array_intersect(['pgv', 'arborizacao', 'iluminacao'], $modulos) !== []; @endphp
                @if ($temInfraPublica)
                {{-- GRUPO: INFRAESTRUTURA (setores fiscais — vindo do antigo "Cadastro Base" —, árvores, postes) --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'infra' ? '' : 'infra'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Infraestrutura</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'infra' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'infra'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">
                        @if (in_array('pgv', $modulos, true))
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="setores_fiscais"
                                class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                {{-- Mesmo marcador (sem cor inline) dos demais itens: no CSS do painel cidadão
                                     ele fica invisível e só garante o mesmo espaçamento --}}
                                <div class="w-3 h-3 rounded-full flex-shrink-0"></div>
                                <span class="layer-text truncate">Setores Fiscais</span>
                            </span></label>
                        @endif
                        @if (in_array('arborizacao', $modulos, true))
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="arvores"
                                class="layer-toggle rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full flex-shrink-0"></div><span
                                    class="layer-text truncate">Arborização Urbana</span>
                            </span>
                        </label>
                        @endif
                        @if (in_array('iluminacao', $modulos, true))
                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"><input type="checkbox"
                                data-layer="postes"
                                class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0"><span
                                class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div><span
                                    class="layer-text truncate">Iluminação Pública</span>
                            </span>
                        </label>
                        @endif
                    </div>
                </div>
                @endif

                @if (in_array('cemiterio', $modulos, true))
                {{-- GRUPO: GESTÃO DE CEMITÉRIOS --}}
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
                @endif

                @if (in_array('rural', $modulos, true))
                {{-- GRUPO: CADASTRO RURAL --}}
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
                @endif
            </div>
        </div>

        {{-- D8 (2026-09-05): FICHA SÓ LEITURA das feições de Mobilidade Urbana. Preenchida pelo
             engine (window.mobFichaPublica) — dentro do wire:ignore para o Livewire não mexer.
             Estilos inline: o CSS do painel cidadão não tem as classes de cor do Tailwind. --}}
        {{-- Modal centralizado e largo (pedido 2026-09-05): fundo escurecido fecha ao clicar, Esc também. --}}
        <div id="mob-ficha-publica" style="display:none; position:fixed; inset:0; z-index:60;">
            <div onclick="window.mobFichaFechar()" style="position:absolute; inset:0; background:rgba(15,23,42,.35); -webkit-backdrop-filter:blur(2px); backdrop-filter:blur(2px);"></div>
            <div style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(720px, 94vw); max-height:84vh; overflow-y:auto; background:#ffffff; border-radius:16px; box-shadow:0 24px 60px -20px rgba(15,23,42,.5); padding:18px 22px 16px; font-size:13px; color:#1f2937;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #f3f4f6;">
                    <div style="min-width:0;">
                        <div id="mob-ficha-camada" style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6b7280;"></div>
                        <div id="mob-ficha-titulo" style="font-size:18px; font-weight:800; color:#111827; line-height:1.25;"></div>
                    </div>
                    <button type="button" onclick="window.mobFichaFechar()" title="Fechar"
                        style="border:0; background:#f3f4f6; color:#6b7280; width:32px; height:32px; border-radius:999px; font-size:20px; line-height:1; cursor:pointer; flex-shrink:0;">&times;</button>
                </div>
                <div id="mob-ficha-corpo"></div>
                <p style="margin:12px 0 0; font-size:11px; color:#9ca3af;">Consulta pública, somente leitura.</p>
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
                {{-- T1.4 (item 3-9) — imagem frontal do imóvel --}}
                <div class="mb-4">
                    @if ($loteFotoFrontal)
                        <a href="{{ $loteFotoFrontal }}" target="_blank" title="Ampliar foto">
                            <img src="{{ $loteFotoFrontal }}" alt="Foto frontal do imóvel"
                                class="w-full h-36 object-cover rounded-xl border border-gray-200 dark:border-gray-600 shadow-sm" />
                        </a>
                        <p class="text-[10px] text-gray-400 mt-1 text-center">Imagem frontal do imóvel</p>
                    @else
                        <div
                            class="w-full h-24 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-700/30">
                            <x-heroicon-o-photo class="w-6 h-6 text-gray-300" />
                            <p class="text-[11px] text-gray-400 mt-1">Sem imagem frontal cadastrada</p>
                        </div>
                    @endif
                </div>

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

                    {{-- T2.4 (item 3-12) — VIABILIDADE DE PARCELAMENTO --}}
                    <button wire:click="mountAction('consultarParcelamento')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">
                            Viabilidade de Parcelamento
                        </span>
                        <x-heroicon-o-scissors class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
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

                    {{-- BOTÃO DE CROQUI DE LOCALIZAÇÃO — T1.4 (item 3-9): em destaque,
                         é a "Planta Cartográfica" que o edital pede junto da imagem frontal.
                         Estilo inline: as classes de cor do Tailwind não estão no CSS
                         compilado do painel cidadão (purge), então azul via CSS direto. --}}
                    <button wire:click="mountAction('exportarCroqui')"
                        style="width:100%;text-align:left;padding:12px 16px;background-color:#2563eb;border:1px solid #2563eb;border-radius:12px;box-shadow:0 2px 6px rgba(37,99,235,.35);display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:background-color .15s;"
                        onmouseover="this.style.backgroundColor='#1d4ed8'"
                        onmouseout="this.style.backgroundColor='#2563eb'">
                        <span style="font-size:14px;font-weight:700;color:#ffffff;display:flex;align-items:center;gap:8px;">
                            <x-heroicon-o-map style="width:16px;height:16px;color:#ffffff;" />
                            Planta / Croqui do Imóvel
                        </span>
                        <x-heroicon-o-arrow-down-tray style="width:16px;height:16px;color:#dbeafe;" />
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
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key', '') }}&libraries=geometry" async
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
        // T2.4 — captura o croqui e gera o PDF oficial de PARCELAMENTO (mesma mecânica
        // da viabilidade de funcionamento, trocando só a chamada Livewire no final)
        window.capturarMapaEImprimirParcelamento = function(loteId, qtdLotes) {
            let featureToHighlight = null;
            if (window.loadedLayers && window.loadedLayers['lotes']) {
                const source = window.loadedLayers['lotes'].getSource();
                featureToHighlight = source.getFeatures().find(f => f.get('id') == loteId);
                if (featureToHighlight) {
                    featureToHighlight.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({ color: '#ff0000', width: 4 }),
                        fill: new ol.style.Fill({ color: 'rgba(250, 204, 21, 0.5)' })
                    }));
                }
            }

            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        const mapaElement = document.getElementById('sigweb-map');
                        mapCanvas.width = mapaElement.clientWidth;
                        mapCanvas.height = mapaElement.clientHeight;
                        const mapContext = mapCanvas.getContext('2d');
                        mapContext.fillStyle = '#ffffff';
                        mapContext.fillRect(0, 0, mapCanvas.width, mapCanvas.height);

                        Array.prototype.forEach.call(canvases, function(canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);
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

                        mapContext.globalAlpha = 1;
                        mapContext.setTransform(1, 0, 0, 1, 0, 0);

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirParcelamento(dataURL, qtdLotes, loteId);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para o Parcelamento:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    if (featureToHighlight) featureToHighlight.setStyle(null);
                }
            }, 800);
        };

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
                    fetch(
                            `/api/search-lote?tenant_id=${window.mapConfig.tenantId}&termo=${encodeURIComponent(this.termo)}&publico=1`)
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

    {{-- PAINEL DE FILTROS ATIVOS (TEMATIZAÇÕES) --}}
    <div id="painel-filtros-ativos"
        style="
        display: none;
        position: absolute;
        bottom: 40px;
        left: 16px;
        z-index: 1000;
        background: rgba(17, 24, 39, 0.92);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 10px;
        min-width: 260px;
        max-width: 320px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.4);
    ">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <span
                style="font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em;">
                🎨 Tematizações Ativas
            </span>
            <button onclick="$wire.limparFiltroAvancado()"
                style="font-size:10px; padding:3px 8px; border-radius:6px;
                background:rgba(239,68,68,0.2); color:#f87171;
                border:1px solid rgba(239,68,68,0.3); cursor:pointer;">
                Limpar Todos
            </button>
        </div>
        <div id="lista-filtros-ativos" style="display:flex; flex-direction:column; gap:4px;"></div>
    </div>

    <x-filament-actions::modals />
</div>
