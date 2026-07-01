<div class="relative w-screen h-screen overflow-hidden bg-gray-100 dark:bg-gray-900 font-sans text-gray-800">

    {{-- 🛡️ ÁREA PROTEGIDA DO LIVEWIRE 🛡️ --}}
    <div wire:ignore>
        {{-- Importação de Estilos (CDN + Seu CSS) --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <link rel="stylesheet" href="{{ asset('css/gis/mapa-sigweb.css') }}">

        {{-- OVERLAY DE CARREGAMENTO PARA IMPRESSÃO --}}
        <div id="print-loading-overlay"
            style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 10000; flex-direction: column; align-items: center; justify-content: center; color: white;">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-white mb-4"></div>
            <h2 id="print-status-text" class="text-xl font-bold">Processando Mapa...</h2>
            <p class="text-sm opacity-80">Isso pode levar alguns segundos para formatos grandes (A1/A0).</p>
        </div>

        {{-- 1. CONFIGURAÇÃO GLOBAL (Ponte PHP -> JS) --}}
        <script>
            window.mapConfig = {
                tenantId: {{ $tenantId }},
                tenantSlug: '{{ $tenantSlug }}',
                mapLat: {{ $mapLat }},
                mapLon: {{ $mapLon }},
                mapZoom: {{ $mapZoom }},
                azureMapsKey: '{{ env('AZURE_MAPS_KEY', '') }}',
                permissionsUrl: '/gis/{{ $tenantSlug }}/map-permissions'
            };
        </script>

        {{-- O MAPA --}}
        <div id="sigweb-map" class="absolute inset-0 z-0 w-full h-full"></div>

        {{-- #12 — COORDENADA DO CURSOR EM TEMPO REAL (UTM SIRGAS 2000 + Lat/Lon) --}}
        {{-- Escondido enquanto a ficha do imóvel estiver aberta para não atrapalhar os botões da ficha. --}}
        <div id="coord-display" x-data="{ fichaAberta: @entangle('showFicha') }" x-show="!fichaAberta"
            style="position:absolute;bottom:8px;left:16px;z-index:500;
                   background:rgba(17,24,39,.75);backdrop-filter:blur(4px);
                   color:#f9fafb;font:11px monospace;padding:4px 10px;
                   border-radius:8px;pointer-events:none;
                   border:1px solid rgba(255,255,255,.1);letter-spacing:.03em;
                   display:flex;flex-direction:column;gap:1px;">
            <div id="coord-utm">E: —&nbsp;&nbsp;N: —&nbsp;&nbsp;EPSG: —</div>
            <div id="coord-latlon" style="opacity:.65;font-size:10px;">Lat: —&nbsp;&nbsp;Lon: —</div>
        </div>

        {{-- TOOLTIP DE MEDIÇÃO (Escondido) --}}
        <div id="measure-tooltip" class="ol-tooltip" style="display: none;"></div>

        {{-- NOVO: TOOLTIP DE HOVER PARA LOGRADOUROS --}}
        <div id="feature-tooltip"
            class="fixed bg-gray text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-xl pointer-events-none z-[9999] ol-tooltip-logradouro"
            style="display: none; transform: translate(-50%, -150%);"></div>

        {{-- BARRA SUPERIOR E CONTROLES --}}
        <div class="absolute top-4 left-0 w-full px-4 z-40 pointer-events-none flex items-start justify-between">

            {{-- seta para voltar --}}
            <div class="pointer-events-auto">
                <a href="/app/{{ $tenantSlug }}"
                    class="bg-white dark:bg-gray-800/95 shadow-lg border border-gray-200 dark:border-gray-700 px-4 py-2.5 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition-all flex items-center gap-2">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                    <span class="hidden sm:inline">Painel</span>
                </a>
            </div>

            {{-- Painel principal de pesquisa e botões --}}
            <div
                class="flex items-center gap-2 pointer-events-auto bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 rounded-2xl max-w-4xl w-full mx-4">

                {{-- Busca Integrada com AUTOCOMPLETE (Alpine.js) --}}
                <div x-data="loteSearch()"
                    class="relative flex items-center flex-1 min-w-[200px] border-r border-gray-100 dark:border-gray-700 px-2"
                    x-ref="inputWrapper">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 mr-2" />
                    <input type="text" x-model="termo" @input.debounce.500ms="buscar(); posicionarDropdown()"
                        @keydown.enter="buscar()" x-ref="inputField" placeholder="Pesquisar..."
                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 dark:text-gray-200 outline-none">

                    {{-- Spinner de Carregando --}}
                    <div x-show="loading" style="display: none;" class="absolute right-3">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-500"></div>
                    </div>

                    {{-- Lista Suspensa de Múltiplas Escolhas --}}
                    <div x-show="resultados.length > 0" style="display: none;" @click.outside="resultados = []"
                        x-ref="dropdown" :style="dropdownStyle"
                        class="fixed bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-[9999]">

                        <ul class="overflow-y-auto custom-scrollbar" :style="`max-height: ${dropdownMaxHeight}px`">
                            <template x-for="(res, index) in resultados" :key="index">
                                <li @click="voarPara(res)"
                                    class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer transition-colors flex items-start gap-3">

                                    {{-- Ícone muda dependendo se é lote, rua ou bairro --}}
                                    <template x-if="res.tipo === 'lote'">
                                        <x-heroicon-o-map-pin class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                    </template>
                                    <template x-if="res.tipo === 'logradouro'">
                                        <x-heroicon-o-minus class="w-5 h-5 text-slate-500 flex-shrink-0 mt-0.5" />
                                    </template>
                                    <template x-if="res.tipo === 'bairro'">
                                        <x-heroicon-o-map class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                                    </template>
                                    <template x-if="res.tipo === 'edificio'">
                                        <x-heroicon-o-building-office
                                            class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" />
                                    </template>

                                    <template x-if="res.tipo === 'setor'">
                                        <x-heroicon-o-rectangle-group
                                            class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" />
                                    </template>
                                    <template x-if="res.tipo === 'distrito'">
                                        <x-heroicon-o-globe-americas
                                            class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" />
                                    </template>

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

                {{-- FERRAENTAS SOLTAS NA BARRA PRINCIPAL --}}
                <div class="flex items-center gap-1 px-1">

                    {{-- BOTÃO FILTRO AVANÇADO --}}
                    <button type="button" x-data="{ ativo: @entangle('filtroAvancadoAtivo') }"
                        x-on:click="$wire.mountAction('filtroAvancadoAction')"
                        class="relative rounded-lg transition-colors flex items-center justify-center text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        title="Filtro Avançado / Tematização">
                        <x-heroicon-o-funnel class="w-5 h-5" />
                    </button>

                    {{-- BOTÃO ESTATÍSTICAS --}}
                    <button type="button" x-on:click="$wire.mountAction('estatisticasAction')"
                        class="relative rounded-lg transition-colors flex items-center justify-center text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        title="Estatísticas por Área">
                        <x-heroicon-o-chart-bar class="w-5 h-5" />
                    </button>

                    {{-- ZOOM EXTENSÃO + VISÃO ANTERIOR --}}
                    <button onclick="window.zoomExtensao()" title="Visão Geral (Zoom Extensão)"
                        class="p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors">
                        <x-heroicon-o-home class="w-5 h-5" />
                    </button>
                    <button onclick="window.visaoAnterior()" title="Visão Anterior"
                        class="p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors">
                        <x-heroicon-o-arrow-uturn-left class="w-5 h-5" />
                    </button>

                    {{-- B4: SALVAR ENQUADRAMENTO PADRÃO --}}
                    @if (filament()->getTenant() &&
                            auth()->user()
                                ?->hasAnyRole(['Master', 'Manager']))
                        <button
                            onclick="(function(){ const e = window.getEnquadramentoAtual?.(); if(e) { @this.call('salvarEnquadramento', e.lat, e.lon, e.zoom); } })()"
                            title="Salvar enquadramento atual como padrão"
                            class="p-2 text-gray-600 hover:bg-amber-100 dark:text-gray-300 dark:hover:bg-amber-900/30 rounded-xl transition-colors">
                            <x-heroicon-o-bookmark class="w-5 h-5" />
                        </button>
                    @endif

                    {{-- B2: IR PARA COORDENADA LAT/LON + ESCALA --}}
                    <div x-data="{ aberto: false }" class="relative" @click.outside="aberto = false">
                        <button @click="aberto = !aberto" title="Ir para coordenada / escala"
                            class="p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl transition-colors flex items-center gap-1">
                            <x-heroicon-o-map-pin class="w-5 h-5" />
                        </button>
                        <div x-show="aberto" x-cloak @keydown.escape="aberto = false"
                            class="absolute top-10 left-0 z-50 bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700 rounded-xl p-3 min-w-[300px] space-y-2">

                            {{-- Linha 1: Lat/Lon --}}
                            <div class="flex gap-2 items-center">
                                <input id="coord-lat" type="number" step="any" placeholder="Latitude"
                                    @keydown.enter="window.irParaCoordenada(document.getElementById('coord-lat').value, document.getElementById('coord-lon').value); aberto = false"
                                    class="w-28 text-xs border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 outline-none">
                                <input id="coord-lon" type="number" step="any" placeholder="Longitude"
                                    @keydown.enter="window.irParaCoordenada(document.getElementById('coord-lat').value, document.getElementById('coord-lon').value); aberto = false"
                                    class="w-28 text-xs border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 outline-none">
                                <button
                                    @click="window.irParaCoordenada(document.getElementById('coord-lat').value, document.getElementById('coord-lon').value); aberto = false"
                                    class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold transition-colors">
                                    Ir
                                </button>
                            </div>

                            {{-- Linha 2: Escala --}}
                            <div class="flex gap-2 items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Escala 1 /</span>
                                <input id="coord-escala" type="number" min="1" step="1"
                                    placeholder="ex: 1000"
                                    @keydown.enter="window.irParaEscala(document.getElementById('coord-escala').value); aberto = false"
                                    class="flex-1 text-xs border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 outline-none">
                                <button
                                    @click="window.irParaEscala(document.getElementById('coord-escala').value); aberto = false"
                                    class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold transition-colors">
                                    Aplicar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>


                    {{-- Mãozinha --}}
                    <button id="btn-pan" title="Mover Mapa (Cancelar Ferramentas)"
                        class="p-2 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl transition-colors focus:outline-none">
                        <x-heroicon-o-hand-raised class="w-5 h-5" />
                    </button>

                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                    {{-- DROPDOWN DE CRIAÇÃO (COM SANFONA) (permissão toolbar_criar_artefatos) --}}
                    <div id="toolbar-criar-artefatos" x-data="{ openDraw: false, activeTabDraw: 'urbano' }" class="relative">
                        <button type="button" @click="openDraw = !openDraw" @click.outside="openDraw = false"
                            title="Desenhar no Mapa"
                            class="p-2 hover:bg-primary-100 dark:hover:bg-primary-900/20 rounded-xl text-gray-600 dark:text-gray-400 transition-colors focus:outline-none flex items-center gap-1">
                            <x-heroicon-o-pencil-square class="w-5 h-5" />
                            <x-heroicon-o-chevron-down class="w-3 h-3" />
                        </button>

                        {{-- A LARGURA FOI FIXADA VIA STYLE INLINE (360px) --}}
                        <div x-show="openDraw" style="display: none; width: 300px;"
                            class="fixed left-0 mt-2 bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50 flex flex-col">

                            {{-- Cabeçalho Fixo --}}
                            <div
                                class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center shadow-sm z-10">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Criar
                                    Artefatos</span>
                            </div>

                            {{-- Área de Rolagem e Sanfonas --}}
                            <div
                                class="overflow-y-auto max-h-[65vh] custom-scrollbar flex flex-col bg-white dark:bg-gray-800">

                                {{-- GRUPO 1: URBANO --}}
                                <div class="border-b border-gray-100 dark:border-gray-700">
                                    {{-- ADICIONADO TYPE="BUTTON" E .PREVENT.STOP --}}
                                    <button type="button"
                                        @click.stop.prevent="activeTabDraw = activeTabDraw === 'urbano' ? '' : 'urbano'"
                                        class="w-full px-4 py-2.5 text-left font-bold text-[11px] text-gray-500 uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-gray-800/50 flex justify-between items-center transition-colors">
                                        <span class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                                            <x-heroicon-o-building-office-2 class="w-4 h-4" /> Cadastro Urbano
                                        </span>
                                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                                            x-bind:class="activeTabDraw === 'urbano' ? 'rotate-180' : ''" />
                                    </button>

                                    <div x-show="activeTabDraw === 'urbano'" x-collapse class="py-1">
                                        <button type="button" onclick="enableDrawing('perimetro_urbano')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-red-50 hover:text-red-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-globe-americas class="w-4 h-4 text-red-500" />
                                            Distrito / Limite (Polígono)
                                        </button>

                                        <button type="button" onclick="enableDrawing('setor_fiscal')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-amber-50 hover:text-amber-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-currency-dollar class="w-4 h-4 text-amber-500" /> Setor
                                        </button>

                                        <button @click="open = false; enableDrawing('zona')"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-globe-americas class="w-4 h-4 text-purple-500" />
                                            Zona de Uso
                                        </button>
                                        <button type="button" onclick="enableDrawing('bairro')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-stop class="w-4 h-4 text-blue-500" /> Bairro (Polígono)
                                        </button>
                                        <button type="button" onclick="enableDrawing('loteamento')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-stop class="w-4 h-4 text-blue-500" /> Loteamento (Polígono)
                                        </button>
                                        <button type="button" onclick="enableDrawing('quadra')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-orange-50 hover:text-orange-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-squares-2x2 class="w-4 h-4 text-orange-500" /> Quadra Urbana
                                        </button>
                                        <button type="button" onclick="enableDrawing('lote')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-emerald-50 hover:text-emerald-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-stop class="w-4 h-4 text-emerald-500" /> Lote (Polígono)
                                        </button>
                                        <button type="button" onclick="enableDrawing('edificacao')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-home class="w-4 h-4 text-amber-600" /> Edificação (Polígono)
                                        </button>

                                        <button type="button" onclick="enableDrawing('logradouro')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-slate-100 hover:text-slate-700 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-minus class="w-4 h-4 text-slate-500" /> Logradouro (Linha)
                                        </button>
                                        <button type="button" onclick="enableDrawing('meio_fio')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-minus class="w-4 h-4 text-amber-700" />
                                            Meio-fio / Calçada (Linha)
                                        </button>

                                    </div>
                                </div>

                                {{-- GRUPO 2: INFRAESTRUTURA E FISCAL --}}
                                <div class="border-b border-gray-100 dark:border-gray-700">
                                    <button type="button"
                                        @click.stop.prevent="activeTabDraw = activeTabDraw === 'infra' ? '' : 'infra'"
                                        class="w-full px-4 py-2.5 text-left font-bold text-[11px] text-gray-500 uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-gray-800/50 flex justify-between items-center transition-colors">
                                        <span class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                            <x-heroicon-o-light-bulb class="w-4 h-4" /> Infra. e outros
                                        </span>
                                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                                            x-bind:class="activeTabDraw === 'infra' ? 'rotate-180' : ''" />
                                    </button>

                                    <div x-show="activeTabDraw === 'infra'" x-collapse
                                        class="py-1 bg-gray-50/30 dark:bg-gray-900/20">
                                        <button type="button" onclick="enableDrawing('patrimonio_publico')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-building-library class="w-4 h-4 text-indigo-500" />
                                            Patrimônio Público
                                        </button>
                                        <button type="button" onclick="enableDrawing('poste')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-yellow-50 hover:text-yellow-600 flex items-center gap-3 transition-colors">
                                            <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z">
                                                </path>
                                            </svg>
                                            Poste / Ponto de Luz
                                        </button>
                                        <button type="button" onclick="enableDrawing('area_reurb')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-amber-50 hover:text-amber-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-home-modern class="w-4 h-4 text-amber-500" />
                                            Área de Regularização (REURB)
                                        </button>
                                        <button type="button" onclick="enableDrawing('arvore')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-emerald-50 hover:text-emerald-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-sparkles class="w-4 h-4 text-emerald-500" /> Árvore
                                        </button>

                                        <button @click="open = false; enableDrawing('ponto_panoramico')"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-camera class="w-4 h-4 text-blue-500" />
                                            Ponto 360º
                                        </button>
                                        <button type="button"
                                            @click="openDraw = false; window.ativarFerramentaToponimiia(true)"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-violet-50 hover:text-violet-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-chat-bubble-bottom-center-text
                                                class="w-4 h-4 text-violet-500" />
                                            Texto / Toponímia
                                        </button>

                                    </div>
                                </div>

                                {{-- GRUPO 3: CEMITÉRIOS --}}
                                <div class="border-b border-gray-100 dark:border-gray-700">
                                    <button type="button"
                                        @click.stop.prevent="activeTabDraw = activeTabDraw === 'cemiterio' ? '' : 'cemiterio'"
                                        class="w-full px-4 py-2.5 text-left font-bold text-[11px] text-gray-500 uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-gray-800/50 flex justify-between items-center transition-colors">
                                        <span class="flex items-center gap-2 text-purple-600 dark:text-purple-400">
                                            <x-heroicon-o-view-columns class="w-4 h-4" /> Cemitérios
                                        </span>
                                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                                            x-bind:class="activeTabDraw === 'cemiterio' ? 'rotate-180' : ''" />
                                    </button>

                                    <div x-show="activeTabDraw === 'cemiterio'" x-collapse class="py-1">
                                        <button type="button" onclick="enableDrawing('cemiterio')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-purple-50 hover:text-purple-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-stop class="w-4 h-4 text-purple-600" /> Cemitério Base
                                            (Polígono)
                                        </button>
                                        <button type="button" onclick="enableDrawing('quadra_cemiterio')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-squares-2x2 class="w-4 h-4 text-indigo-500" /> Quadra de
                                            Cemitério
                                        </button>
                                        <button type="button" onclick="enableDrawing('logradouro_cemiterio')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-slate-100 hover:text-slate-700 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-arrows-right-left class="w-4 h-4 text-slate-500" /> Rua
                                            Interna
                                        </button>
                                        <button type="button" onclick="enableDrawing('jazigo')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-stone-100 hover:text-stone-700 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-archive-box class="w-4 h-4 text-stone-600" /> Jazigo / Túmulo
                                        </button>
                                    </div>
                                </div>

                                {{-- GRUPO 4: RURAL --}}
                                <div>
                                    <button type="button"
                                        @click.stop.prevent="activeTabDraw = activeTabDraw === 'rural' ? '' : 'rural'"
                                        class="w-full px-4 py-2.5 text-left font-bold text-[11px] text-gray-500 uppercase tracking-wider hover:bg-gray-50 dark:hover:bg-gray-800/50 flex justify-between items-center transition-colors">
                                        <span class="flex items-center gap-2 text-stone-600 dark:text-stone-400">
                                            <x-heroicon-o-globe-americas class="w-4 h-4" /> Zona Rural
                                        </span>
                                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                                            x-bind:class="activeTabDraw === 'rural' ? 'rotate-180' : ''" />
                                    </button>

                                    <div x-show="activeTabDraw === 'rural'" x-collapse
                                        class="py-1 bg-stone-50/50 dark:bg-stone-900/20">
                                        <button type="button" onclick="enableDrawing('rural_localidade')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-stone-100 hover:text-stone-800 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-map class="w-4 h-4 text-stone-600" /> Localidade / Distrito
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_propriedade')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-stone-100 hover:text-stone-800 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-home-modern class="w-4 h-4 text-stone-600" /> Propriedade
                                            (CAR)
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_estrada')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-stone-100 hover:text-stone-800 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-lifebuoy class="w-4 h-4 text-stone-600" /> Estrada / Vicinal
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_hidro_linha')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-cyan-50 hover:text-cyan-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-minus class="w-4 h-4 text-cyan-500" /> Rio / Córrego (Linha)
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_hidro_poligono')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-cyan-50 hover:text-cyan-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-stop class="w-4 h-4 text-cyan-500" /> Lago / Represa
                                            (Polígono)
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_hidro_ponto')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-cyan-50 hover:text-cyan-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-sparkles class="w-4 h-4 text-cyan-500" /> Nascente (Ponto)
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_ponte')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-bars-2 class="w-4 h-4 text-amber-600" /> Ponte
                                        </button>
                                        <button type="button" onclick="enableDrawing('rural_ponto_interesse')"
                                            @click="openDraw = false"
                                            class="w-full px-6 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-teal-50 hover:text-teal-600 flex items-center gap-3 transition-colors">
                                            <x-heroicon-o-star class="w-4 h-4 text-teal-500" /> Ponto de Interesse
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- GRUPO FERRAMENTAS (permissão toolbar_ferramentas) --}}
                    <div id="toolbar-ferramentas" class="flex items-center gap-0.5">

                        {{-- 🛠️ DROPDOWN DE FERRAMENTAS 🛠️ --}}
                        <div x-data="{ openTools: false }" class="relative">
                            <button @click="openTools = !openTools" @click.outside="openTools = false"
                                title="Ferramentas Avançadas"
                                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors focus:outline-none flex items-center gap-1">
                                <x-heroicon-o-wrench-screwdriver class="w-5 h-5" />
                                <x-heroicon-o-chevron-down class="w-3 h-3" />
                            </button>
                            <div x-show="openTools" style="display: none;"
                                class="fixed mt-2 w-[240px] bg-white dark:bg-gray-800 shadow-2xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-[9999]"
                                style="left: 360px;"> {{-- Ajuste a posição left se precisar --}}
                                <div
                                    class="px-3 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-700">
                                    <span
                                        class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Ferramentas</span>
                                </div>
                                <div class="py-1 flex flex-col">

                                    <button id="btn-tool-numeracao" @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 flex items-center gap-2 font-bold transition-colors">
                                        <x-heroicon-o-hashtag class="w-4 h-4 text-blue-500" /> Numeração Predial
                                    </button>

                                    <button id="btn-tool-altimetria" @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-emerald-50 dark:hover:bg-gray-700 hover:text-emerald-600 flex items-center gap-2 font-bold transition-colors">
                                        <x-heroicon-o-chart-bar class="w-4 h-4 text-emerald-500" /> Perfil de Terreno
                                        (Altimetria)
                                    </button>

                                    <button id="btn-tool-unificar" @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-gray-700 hover:text-purple-600 flex items-center gap-2 font-bold transition-colors">
                                        <x-heroicon-o-link class="w-4 h-4 text-purple-500" /> Unificar Lotes (Solda)
                                    </button>

                                    <button id="btn-tool-azimute" @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 flex items-center gap-2 font-bold transition-colors">
                                        <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-blue-500" /> Criar por Azimutes
                                    </button>

                                    <button wire:click="mountAction('abrirNuvemPontosAction')"
                                        @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 flex items-center gap-2 font-bold transition-colors">
                                        <x-heroicon-o-cube class="w-4 h-4 text-blue-500" /> Visualizador 3D (LiDAR)
                                    </button>

                                    <button wire:click="mountAction('configurarPgvAction')" @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-emerald-50 dark:hover:bg-gray-700 hover:text-emerald-600 flex items-center gap-2 font-bold transition-colors border-b border-gray-100 pb-3 mb-1">
                                        <x-heroicon-o-banknotes class="w-4 h-4 text-emerald-500" /> Simulador de
                                        Valores
                                        (PGV)
                                    </button>

                                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                                    <button id="btn-measure-line" @click="openTools = false"
                                        class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors">
                                        <x-heroicon-o-arrows-right-left class="w-4 h-4 text-gray-500" /> Medir
                                        Distância
                                    </button>

                                    <button id="btn-measure-area" @click="openTools = false"
                                        class="w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors">
                                        <x-heroicon-o-view-columns class="w-4 h-4 text-gray-500" /> Medir Área
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- DROPDOWN IMPRESSÃO E EXPORTAÇÃO --}}
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open" title="Impressão e Exportação"
                                class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-2 font-bold text-sm">
                                <x-heroicon-o-printer class="w-5 h-5" />
                                <span class="hidden md:inline">Imprimir / Exportar</span>
                                <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform"
                                    x-bind:class="open ? 'rotate-180' : ''" />
                            </button>

                            <div x-show="open"
                                style="display: none; background-color: white; border: 1px solid #e5e7eb;"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-0 mt-2 w-72 dark:bg-gray-800 rounded-xl shadow-2xl z-[1001] overflow-hidden">

                                <div class="p-2">
                                    <div
                                        class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                        Formatos de Impressão (PDF)</div>

                                    @foreach (['A4', 'A3', 'A2', 'A1', 'A0'] as $formato)
                                        <div class="flex items-center gap-1 px-2 mb-1">
                                            <button
                                                @click="$dispatch('gerar-pdf-mapa', { size: '{{ $formato }}', orientation: 'portrait' }); open = false"
                                                style="text-align: left; background: none; border: none; padding: 6px 12px; font-size: 12px; color: #374151; flex: 1; cursor: pointer;"
                                                class="hover:bg-blue-50 rounded-lg">
                                                {{ $formato }} - Retrato
                                            </button>
                                            <button
                                                @click="$dispatch('gerar-pdf-mapa', { size: '{{ $formato }}', orientation: 'landscape' }); open = false"
                                                style="background-color: #eff6ff; color: #1d4ed8; font-size: 10px; font-weight: bold; border: none; padding: 4px 8px; border-radius: 6px; cursor: pointer;">
                                                PAISAGEM
                                            </button>
                                        </div>
                                    @endforeach

                                    <div style="margin: 8px 0; border-top: 1px solid #f3f4f6;"></div>

                                    <div
                                        class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                        Exportar Camadas (SHP)</div>

                                    @php
                                        $camadasShp = [
                                            'lotes' => 'Lotes',
                                            'edificacoes' => 'Edificações',
                                            'logradouros' => 'Logradouros',
                                            'quadras' => 'Quadras',
                                            'bairros' => 'Bairros',
                                            'loteamentos' => 'Loteamentos',
                                            'zonas' => 'Zonas',
                                            'perimetros_urbanos' => 'Distritos / Limites',
                                            'setores_fiscais' => 'Setores',
                                            'arvores' => 'Árvores',
                                            'postes' => 'Postes',
                                            'cemiterios' => 'Cemitérios',
                                            'rural_propriedades' => 'Propriedades Rurais',
                                            'rural_estradas' => 'Estradas Rurais',
                                            'rural_pontes' => 'Pontes Rurais',
                                            'rural_localidades' => 'Localidades Rurais',
                                            'rural_hidrografias' => 'Hidrografias',
                                        ];
                                    @endphp

                                    <div style="max-height: 240px; overflow-y: auto;">
                                        @foreach ($camadasShp as $key => $label)
                                            <button
                                                @click="$dispatch('exportar-camada-shp', { layer: '{{ $key }}' }); open = false"
                                                style="width: 100%; text-align: left; background: none; border: none; padding: 6px 12px; font-size: 12px; color: #374151; display: flex; align-items: center; gap: 8px; cursor: pointer;"
                                                class="hover:bg-emerald-50 rounded-lg">
                                                <x-heroicon-o-arrow-down-tray class="w-4 h-4 text-emerald-500" />
                                                {{ $label }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>{{-- /toolbar-ferramentas --}}


                    {{-- BOTÃO DA ALTERNANCIA DE MAPAS --}}
                    {{-- <button id="btn-satelite" title="Alternar Mapa Base"
                        class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-2 font-bold text-sm">
                        <x-heroicon-o-globe-americas class="w-5 h-5" />
                        <span id="satelite-text" class="hidden md:inline">Satélite</span>
                    </button> --}}

                    {{-- DROPDOWN DE MAPAS BASE (BASEMAPS) --}}
                    <div class="relative" x-data="{ open: false, activeBasemap: 'osm' }" @click.away="open = false"
                        @sync-basemap-ui.window="activeBasemap = $event.detail">
                        <button @click="open = !open" title="Alternar Mapa Base"
                            class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-gray-600 dark:text-gray-300 transition-colors flex items-center gap-2 font-bold text-sm">
                            <x-heroicon-o-globe-americas class="w-5 h-5" />
                            <span class="hidden md:inline">Mapas Base</span>
                            <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform"
                                x-bind:class="open ? 'rotate-180' : ''" />
                        </button>

                        <div x-show="open" style="display: none; width: 250px;"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute left-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 z-[1001] overflow-hidden">

                            <div class="p-2 space-y-1">
                                {{-- Grupo: Vetoriais/Ruas --}}
                                <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                    Mapas de Ruas</div>

                                <button
                                    @click="activeBasemap = 'osm'; $dispatch('switch-basemap', 'osm'); open = false"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 text-sm transition-colors"
                                    x-bind:class="activeBasemap === 'osm' ? 'text-primary-600 font-bold' :
                                        'text-gray-700 dark:text-gray-300'">
                                    <span class="flex items-center gap-2"><x-heroicon-o-map class="w-4 h-4" />
                                        OpenStreetMap</span>
                                    <x-heroicon-o-check x-show="activeBasemap === 'osm'" class="w-4 h-4" />
                                </button>

                                <button
                                    @click="activeBasemap = 'azure_road'; $dispatch('switch-basemap', 'azure_road'); open = false"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 text-sm transition-colors"
                                    x-bind:class="activeBasemap === 'azure_road' ? 'text-primary-600 font-bold' :
                                        'text-gray-700 dark:text-gray-300'">
                                    <span class="flex items-center gap-2"><x-heroicon-o-map-pin class="w-4 h-4" />
                                        Azure Maps (Ruas)</span>
                                    <x-heroicon-o-check x-show="activeBasemap === 'azure_road'" class="w-4 h-4" />
                                </button>

                                {{-- Grupo: Satélite e Ortofoto --}}
                                <div
                                    class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2 border-t border-gray-100 dark:border-gray-700">
                                    Imagens Aéreas</div>

                                <button
                                    @click="activeBasemap = 'azure_sat'; $dispatch('switch-basemap', 'azure_sat'); open = false"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 text-sm transition-colors"
                                    x-bind:class="activeBasemap === 'azure_sat' ? 'text-primary-600 font-bold' :
                                        'text-gray-700 dark:text-gray-300'">
                                    <span class="flex items-center gap-2"><x-heroicon-o-camera class="w-4 h-4" />
                                        Azure Satélite</span>
                                    <x-heroicon-o-check x-show="activeBasemap === 'azure_sat'" class="w-4 h-4" />
                                </button>

                                <button
                                    @click="activeBasemap = 'esri_sat'; $dispatch('switch-basemap', 'esri_sat'); open = false"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 text-sm transition-colors"
                                    x-bind:class="activeBasemap === 'esri_sat' ? 'text-primary-600 font-bold' :
                                        'text-gray-700 dark:text-gray-300'">
                                    <span class="flex items-center gap-2"><x-heroicon-o-globe-asia-australia
                                            class="w-4 h-4" /> Esri World Imagery</span>
                                    <x-heroicon-o-check x-show="activeBasemap === 'esri_sat'" class="w-4 h-4" />
                                </button>

                                {{-- Futuras Ortofotos --}}
                                <button
                                    @click="activeBasemap = 'ortofoto_2025'; $dispatch('switch-basemap', 'ortofoto_2025'); open = false"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 text-sm transition-colors"
                                    x-bind:class="activeBasemap === 'ortofoto_2025' ? 'text-primary-600 font-bold' :
                                        'text-gray-700 dark:text-gray-300'">
                                    <div class="flex flex-col items-start leading-tight">
                                        <span class="flex items-center gap-2"><x-heroicon-o-sparkles
                                                class="w-4 h-4" /> Ortofoto Municipal</span>
                                        <span class="text-[9px] text-gray-400 ml-6">Ano de Referência: 2025</span>
                                    </div>
                                    <x-heroicon-o-check x-show="activeBasemap === 'ortofoto_2025'" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>


                    <button id="btn-toggle-layers" title="Camadas do Mapa"
                        class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-primary-600 dark:text-primary-400 font-bold text-sm flex items-center gap-2">
                        <x-heroicon-o-square-3-stack-3d class="w-5 h-5" />
                        <span class="hidden md:inline">Camadas</span>
                    </button>

                    {{-- BOTÃO TOGGLE CAD AVANÇADO (permissão toolbar_ferramentas) --}}
                    <button type="button" data-permission-group="toolbar:ferramentas"
                        @click="$dispatch('toggle-cad-avancado')"
                        class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl text-primary-600 dark:text-primary-400 font-bold text-sm flex items-center gap-2"
                        title="Módulo CAD Avançado">
                        <x-heroicon-o-cpu-chip class="w-5 h-5" />
                        <span class="hidden md:inline text-sm">CAD</span>
                    </button>

                </div>
            </div>
            <div class="w-10"></div>
        </div>

        {{-- BARRA FLUTUANTE DE EDIÇÃO ESPACIAL (Alpine.js) --}}
        <div x-data="{ editandoId: null, modoLocal: 'mover' }" x-show="editandoId !== null" {{-- MÁGICA: Ao iniciar a edição, zera o input de rotação --}}
            @iniciar-edicao.window="
                editandoId = $event.detail.id;
                modoLocal = 'mover';
                if(document.getElementById('slider-rotacao')) {
                    document.getElementById('slider-rotacao').value = 0;
                    document.getElementById('input-rotacao').value = 0;
                }
            "
            @encerrar-edicao.window="editandoId = null"
            style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-emerald-500 px-4 py-2 flex items-center gap-3 animate-bounce-short">

            {{-- BARRA PRINCIPAL (BOTÕES) --}}
            <div
                class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-emerald-500 px-4 py-2 flex items-center gap-3 pointer-events-auto animate-bounce-short">

                <span
                    class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2 pr-2 hidden sm:flex">
                    <span class="relative flex h-3 w-3">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    Editando...
                </span>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>

                {{-- TOGGLE MOVER / REDIMENSIONAR / GIRAR --}}
                <div
                    class="flex items-center bg-gray-100 dark:bg-gray-900 rounded-full p-1 border border-gray-200 dark:border-gray-700">
                    <button @click="modoLocal = 'mover'; window.alternarFerramentaEdicao('mover');"
                        :class="modoLocal === 'mover' ?
                            'bg-white dark:bg-gray-700 shadow text-emerald-600 dark:text-emerald-400' :
                            'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1 transition-all">
                        <x-heroicon-o-arrows-pointing-out class="w-4 h-4" /> Arrastar
                    </button>
                    <button @click="modoLocal = 'redimensionar'; window.alternarFerramentaEdicao('redimensionar');"
                        :class="modoLocal === 'redimensionar' ?
                            'bg-white dark:bg-gray-700 shadow text-blue-600 dark:text-blue-400' :
                            'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1 transition-all">
                        <x-heroicon-o-squares-plus class="w-4 h-4" /> Pontos
                    </button>
                    <button @click="modoLocal = 'girar'; window.alternarFerramentaEdicao('girar');"
                        :class="modoLocal === 'girar' ?
                            'bg-white dark:bg-gray-700 shadow text-purple-600 dark:text-purple-400' :
                            'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1 transition-all">
                        <x-heroicon-o-arrow-path class="w-4 h-4" /> Girar
                    </button>
                </div>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

                <button onclick="salvarEdicaoGeometria()"
                    class="text-sm font-black text-emerald-600 hover:text-emerald-700 uppercase flex items-center gap-1 transition-transform hover:scale-110">
                    <x-heroicon-o-check class="w-5 h-5" />
                </button>

                <button onclick="cancelarEdicaoGeometria()"
                    class="text-sm font-black text-red-600 hover:text-red-700 uppercase flex items-center gap-1 transition-transform hover:scale-110">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            {{-- 🎛️ MESA DE DESENHO (GIRAR) QUE ABRE EMBAIXO DA BARRA --}}
            <div x-show="modoLocal === 'girar'" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-4"
                style="display: none;"
                class="bg-white dark:bg-gray-800 shadow-2xl rounded-2xl border-2 border-purple-500 px-6 py-4 flex flex-col gap-2 w-[400px] pointer-events-auto">

                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1">
                        <x-heroicon-o-arrow-path class="w-4 h-4 text-purple-500" /> Rotação Exata
                    </label>
                    <span
                        class="text-[10px] text-gray-400 font-bold bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-md">Turf.js</span>
                </div>

                <div class="flex items-center gap-4">
                    {{-- O SLIDER --}}
                    <input type="range" id="slider-rotacao" min="-180" max="180" value="0"
                        step="1"
                        oninput="window.aplicarRotacao(this.value); document.getElementById('input-rotacao').value = this.value;"
                        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-purple-600">

                    {{-- O INPUT MANUAL --}}
                    <div class="relative">
                        <input type="number" id="input-rotacao" value="0"
                            oninput="window.aplicarRotacao(this.value); document.getElementById('slider-rotacao').value = this.value;"
                            class="w-20 pl-2 pr-6 py-1.5 text-center border-2 border-gray-300 dark:border-gray-600 rounded-xl text-sm font-bold text-purple-700 dark:text-purple-400 focus:ring-purple-500 focus:border-purple-500 bg-gray-50 dark:bg-gray-900 transition-all">
                        <span
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-xs pointer-events-none">º</span>
                    </div>
                </div>

                <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1 mt-1">
                    <span>-180º</span>
                    <span>0º</span>
                    <span>+180º</span>
                </div>
            </div>
        </div>

        {{-- BARRA FLUTUANTE DE REVISÃO DA NUMERAÇÃO PREDIAL (Padrão Pílula) --}}
        <div x-data="{ previewAtivo: @entangle('previewNumeracaoAtivo') }" x-show="previewAtivo"
            style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-blue-500 px-5 py-3 flex items-center gap-3 animate-bounce-short flex-wrap justify-center max-w-[95vw]">

            <span class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                </span>
                Numeração Predial
            </span>

            {{-- Legenda de cores (item PoC 100) --}}
            <div class="flex items-center gap-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-green-600"></span>Par</span>
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-blue-600"></span>Ímpar</span>
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span>Excluído</span>
            </div>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button wire:click="inverterLadosNumeracao" title="Trocar o lado par pelo ímpar (item 103)"
                class="text-sm font-black text-indigo-600 hover:text-indigo-700 uppercase transition-colors flex items-center gap-1">
                <x-heroicon-o-arrows-right-left class="w-4 h-4" /> Inverter
            </button>

            <button wire:click="mountAction('revisarNumeracaoAction')" title="Editar número por parcela e ver faixa disponível (item 107)"
                class="text-sm font-black text-slate-600 hover:text-slate-800 uppercase transition-colors flex items-center gap-1">
                <x-heroicon-o-list-bullet class="w-4 h-4" /> Revisar
            </button>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button wire:click="confirmarNumeracaoAction" title="Salvar a numeração para comparação posterior (item 108)"
                class="text-sm font-black text-blue-600 hover:text-blue-700 uppercase transition-colors">
                Salvar
            </button>

            <button wire:click="verDivergenciasNumeracao" title="Mostrar parcelas com número atual diferente do gerado (item 109)"
                class="text-sm font-black text-rose-600 hover:text-rose-700 uppercase transition-colors flex items-center gap-1">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4" /> Divergências
            </button>

            <button onclick="capturarMapaNumeracao()" id="btn-print-num"
                class="text-sm font-black text-amber-600 hover:text-amber-700 uppercase transition-colors flex items-center gap-1">
                <x-heroicon-o-printer class="w-4 h-4" /> PDF
            </button>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button wire:click="cancelarNumeracaoAction"
                class="text-sm font-black text-red-600 hover:text-red-700 uppercase transition-colors">
                Cancelar
            </button>
        </div>

        {{-- PAINEL: CRIAR GEOMETRIA POR AZIMUTES (item PoC 046) --}}
        <div x-data="{
                aberto: false,
                pickando: false,
                lon: '', lat: '', entidade: 'lotes',
                rows: [{ az: null, dist: null }],
                area: 0, perimetro: 0,
                init() {
                    window.addEventListener('abrir-azimute-tool', () => {
                        this.aberto = true;
                        // Já entra em modo 'clique no mapa' para o ponto inicial
                        this.$nextTick(() => this.pegarDoMapa());
                    });
                    window.addEventListener('azimute-ponto-inicial', (e) => {
                        this.lon = e.detail.lon.toFixed(7);
                        this.lat = e.detail.lat.toFixed(7);
                        this.pickando = false;
                        this.recalc();
                    });
                },
                dados() {
                    return {
                        lon: parseFloat(this.lon), lat: parseFloat(this.lat),
                        pares: this.rows.filter(r => r.az !== null && r.az !== '' && r.dist !== null && r.dist !== '')
                                        .map(r => ({ az: parseFloat(r.az), dist: parseFloat(r.dist) }))
                    };
                },
                recalc() {
                    const d = this.dados();
                    if (!d.lon || !d.lat || d.pares.length < 1) { this.area = 0; this.perimetro = 0; window.limparAzimutePreview(); return; }
                    const res = window.previewAzimutes(d.lon, d.lat, d.pares) || {};
                    this.area = res.area || 0; this.perimetro = res.perimetro || 0;
                },
                addLinha() { this.rows.push({ az: null, dist: null }); },
                delLinha(i) { this.rows.splice(i, 1); this.recalc(); },
                pegarDoMapa() { this.pickando = true; window.iniciarAzimutePickStart(); },
                finalizar() {
                    const d = this.dados();
                    if (!d.lon || !d.lat) { alert('Informe o ponto inicial (Lon/Lat) ou pegue do mapa.'); return; }
                    if (d.pares.length < 2) { alert('Informe ao menos 2 arestas (azimute + distância).'); return; }
                    window.finalizarAzimutes(d.lon, d.lat, d.pares, this.entidade);
                    this.aberto = false;
                },
                limpar() { this.rows = [{ az: null, dist: null }]; this.area = 0; this.perimetro = 0; window.limparAzimutePreview(); },
                fechar() { this.aberto = false; this.pickando = false; window.limparAzimutePreview(); }
            }"
            x-show="aberto" x-cloak
            style="display:none; position: fixed; top: 70px; right: 16px; z-index: 9999; width: 330px;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-xl border-2 border-gray-300 dark:border-gray-600 p-4 flex flex-col gap-3">

            <div class="flex items-center justify-between">
                <span class="font-bold text-sm text-gray-700 dark:text-gray-200 flex items-center gap-2">
                    <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-orange-500" /> Criar por Azimutes
                </span>
                <button type="button" @click="fechar()" class="text-gray-400 hover:text-gray-600">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>

            <div>
                <label class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">Ponto inicial (Lon / Lat)</label>
                <div class="flex gap-1">
                    <input type="number" step="any" x-model="lon" @input="recalc()" placeholder="Longitude"
                        class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-900">
                    <input type="number" step="any" x-model="lat" @input="recalc()" placeholder="Latitude"
                        class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-900">
                </div>
                <button type="button" @click="pegarDoMapa()"
                    class="mt-1 text-[11px] font-semibold flex items-center gap-1"
                    :class="pickando ? 'text-red-600 animate-pulse' : 'text-blue-600 hover:text-blue-700'">
                    <x-heroicon-o-map-pin class="w-3 h-3" />
                    <span x-text="pickando ? 'Clique no mapa...' : 'Pegar do mapa'"></span>
                </button>
            </div>

            <div>
                <label class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">Salvar como</label>
                <select x-model="entidade"
                    class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-900">
                    <option value="lotes">Lote</option>
                    <option value="quadras">Quadra</option>
                    <option value="bairros">Bairro</option>
                    <option value="loteamentos">Loteamento</option>
                    <option value="perimetros">Perímetro Urbano</option>
                    <option value="zonas">Zona</option>
                    <option value="setores_fiscais">Setor Fiscal</option>
                    <option value="areas_reurb">Área REURB</option>
                    <option value="patrimonio_publicos">Patrimônio Público</option>
                </select>
            </div>

            <div>
                <label class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">Arestas — Azimute (°) / Distância (m)</label>
                <div class="flex flex-col gap-1 max-h-44 overflow-y-auto pr-1">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="flex gap-1 items-center">
                            <span class="text-[10px] text-gray-400 w-4 text-right" x-text="i + 1"></span>
                            <input type="number" step="any" x-model="row.az" @input="recalc()" placeholder="Az°"
                                class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-900">
                            <input type="number" step="any" x-model="row.dist" @input="recalc()" placeholder="metros"
                                class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-900">
                            <button type="button" @click="delLinha(i)" class="text-red-500 hover:text-red-700">
                                <x-heroicon-o-minus-circle class="w-4 h-4" />
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addLinha()"
                    class="mt-1 text-[11px] text-blue-600 hover:text-blue-700 font-semibold flex items-center gap-1">
                    <x-heroicon-o-plus-circle class="w-3 h-3" /> Adicionar aresta
                </button>
            </div>

            <div class="text-[11px] text-gray-500 dark:text-gray-400" x-show="area > 0">
                Área: <b x-text="area.toLocaleString('pt-BR', {maximumFractionDigits: 2})"></b> m² ·
                Perímetro: <b x-text="perimetro.toLocaleString('pt-BR', {maximumFractionDigits: 2})"></b> m
            </div>

            <div class="flex gap-2">
                <button type="button" @click="finalizar()"
                    class="flex-1 text-white text-xs font-bold rounded px-2 py-2 transition-colors"
                    style="background-color: rgb(var(--primary-600, 37 99 235));"
                    onmouseover="this.style.backgroundColor='rgb(var(--primary-500, 59 130 246))'"
                    onmouseout="this.style.backgroundColor='rgb(var(--primary-600, 37 99 235))'">
                    Enviar p/ Mesa de Desenho
                </button>
                <button type="button" @click="limpar()"
                    class="text-xs text-gray-500 border border-gray-300 dark:border-gray-600 rounded px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Limpar
                </button>
            </div>
            <p class="text-[10px] text-gray-400 leading-tight">
                O polígono é fechado automaticamente. Ao enviar, ajuste se quiser e clique em <b>Salvar</b> na barra da Mesa de Desenho para criar o artefato.
            </p>
        </div>

        {{-- BARRA FLUTUANTE DE REVISÃO DA PGV --}}
        <div x-data="{ previewPgv: @entangle('previewPgvAtivo') }" x-show="previewPgv"
            style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-emerald-500 px-6 py-3 flex items-center gap-4 animate-bounce-short">

            <span class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <span class="relative flex h-3 w-3"><span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span
                        class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span></span>
                Revisão da PGV (Simulador Ativo)
            </span>
            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
            <button wire:click="homologarPgvAction"
                class="text-sm font-black text-emerald-600 hover:text-emerald-700 uppercase">
                Salvar & Homologar
            </button>
            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
            <button wire:click="cancelarPgvAction"
                class="text-sm font-black text-red-600 hover:text-red-700 uppercase">
                Cancelar
            </button>
        </div>

        {{-- JANELA DE CAMADAS --}}
        <div id="layers-panel" style="top: 80px; left: calc(100vw - 340px);" x-data="{ activeTab: 'base' }"
            class="absolute bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-2xl rounded-2xl border-2 border-gray-200/50 dark:border-gray-700/50 flex flex-col overflow-hidden z-20 pointer-events-auto">
            <div class="bg-gray-100/50 dark:bg-gray-800/50 border-b border-gray-200/50 dark:border-gray-700/50 px-4 py-3 flex justify-between items-center cursor-grab active:cursor-grabbing"
                id="layers-panel-header">
                <h3
                    class="font-bold text-sm text-gray-700 dark:text-gray-200 flex items-center gap-2 uppercase tracking-wider">
                    <x-heroicon-o-bars-3 class="w-4 h-4 text-gray-500" /> Camadas do Mapa
                </h3>

                {{-- 🛑 BOTÃO DE FECHAR --}}
                <button type="button" onmousedown="event.stopPropagation()"
                    onclick="document.getElementById('layers-panel').classList.add('hidden')"
                    class="p-1 -mr-1 rounded-lg text-gray-400 hover:text-red-500 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all cursor-pointer focus:outline-none"
                    title="Fechar Janela">
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

                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="perimetros"
                                    class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-3 bg-red-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Distritos / Limites</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="perimetros-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'perimetros',enabled:this.checked,field:document.getElementById('perimetros-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="perimetros-label-field"
                                    onchange="if(document.getElementById('perimetros-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'perimetros',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="name">Nome</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="setores_fiscais"
                                    class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-3 bg-red-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Setores</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="setores_fiscais-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'setores_fiscais',enabled:this.checked,field:document.getElementById('setores_fiscais-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="setores_fiscais-label-field"
                                    onchange="if(document.getElementById('setores_fiscais-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'setores_fiscais',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="nome">Nome</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="bairros"
                                    class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Bairros</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="bairros-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'bairros',enabled:this.checked,field:document.getElementById('bairros-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="bairros-label-field"
                                    onchange="if(document.getElementById('bairros-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'bairros',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="name">Nome</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="loteamentos"
                                    class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Loteamentos</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="loteamentos-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'loteamentos',enabled:this.checked,field:document.getElementById('loteamentos-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="loteamentos-label-field"
                                    onchange="if(document.getElementById('loteamentos-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'loteamentos',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="name">Nome</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="quadras"
                                    class="layer-toggle rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-3 bg-orange-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Quadras</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="quadras-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'quadras',enabled:this.checked,field:document.getElementById('quadras-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="quadras-label-field"
                                    onchange="if(document.getElementById('quadras-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'quadras',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="name">Nome</option>
                                    <option value="setor_codigo">Setor</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="lotes"
                                    class="layer-toggle rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div
                                        class="w-3 h-3 bg-emerald-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Lotes</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="lotes-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'lotes',enabled:this.checked,field:document.getElementById('lotes-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="lotes-label-field"
                                    onchange="if(document.getElementById('lotes-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'lotes',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="numero_lote">Nº Lote</option>
                                    <option value="numero_logradouro">Nº Predial</option>
                                    <option value="area_geo">Área m²</option>
                                    <option value="sequential_id">Seq. ID</option>
                                </select>
                            </div>
                        </div>

                        {{-- Sub-linha: Status de Coleta (recolore os lotes pelo status_cadastro) --}}
                        <div data-permission-group="layer:lotes" x-data="{ statusColorOn: false }" class="ml-7 mt-1">
                            <label
                                class="flex items-center gap-2 cursor-pointer text-xs text-gray-600 dark:text-gray-300">
                                <input type="checkbox" x-model="statusColorOn"
                                    @change="window.dispatchEvent(new CustomEvent('sigweb-toggle-status-color',{detail:{enabled:statusColorOn}}))"
                                    class="rounded border-gray-300 w-3 h-3">
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-paint-brush class="w-3 h-3" />
                                    Status de Coleta
                                </span>
                            </label>
                            <div x-show="statusColorOn" x-transition
                                class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1"><span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#10B981"></span>
                                    Coletado</span>
                                <span class="inline-flex items-center gap-1"><span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#F59E0B"></span>
                                    Pendente</span>
                                <span class="inline-flex items-center gap-1"><span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#EF4444"></span>
                                    Inconformidade</span>
                                <span class="inline-flex items-center gap-1"><span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#9CA3AF"></span>
                                    Não visitado</span>
                            </div>
                        </div>

                        {{-- Sub-linha: Processos Digitais (recolore lotes pela etapa atual do processo em andamento) --}}
                        <div data-permission-group="layer:lotes" x-data="{ processosOn: false, legenda: [], aberta: false }" x-init="window.addEventListener('sigweb-processos-legenda', function(e) {
                            legenda = e.detail.items;
                            aberta = false
                        })"
                            class="ml-7 mt-1">
                            <label
                                class="flex items-center gap-2 cursor-pointer text-xs text-gray-600 dark:text-gray-300">
                                <input type="checkbox" x-model="processosOn"
                                    @change="window.dispatchEvent(new CustomEvent('sigweb-toggle-processos-color',{detail:{enabled:processosOn}}))"
                                    class="rounded border-gray-300 w-3 h-3">
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-document-check class="w-3 h-3" />
                                    Processos Digitais
                                </span>
                            </label>

                            {{-- Estado colapsado: dots de cor + "N etapas" (clica para expandir) --}}
                            <div x-show="processosOn && legenda.length > 0 && !aberta" x-transition
                                @click="aberta = true"
                                class="mt-1 flex items-center gap-1.5 cursor-pointer select-none text-[10px] text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                                <span class="flex items-center gap-1 flex-shrink-0">
                                    <template x-for="item in legenda" :key="item.cor">
                                        <span
                                            :style="'display:inline-block;width:8px;height:8px;border-radius:50%;background:' +
                                            item.cor"
                                            :title="item.nome"></span>
                                    </template>
                                </span>
                                <span class="whitespace-nowrap flex-shrink-0 flex items-center gap-0.5">
                                    <span
                                        x-text="legenda.length + (legenda.length === 1 ? ' etapa' : ' etapas')"></span>
                                    <x-heroicon-o-chevron-down class="w-2.5 h-2.5 opacity-60" />
                                </span>
                            </div>

                            {{-- Estado expandido: lista completa + botão recolher --}}
                            <div x-show="processosOn && legenda.length > 0 && aberta" x-transition
                                class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                                <div class="flex flex-wrap gap-x-3 gap-y-1">
                                    <template x-for="item in legenda" :key="item.cor">
                                        <span class="inline-flex items-center gap-1">
                                            <span
                                                :style="'display:inline-block;width:10px;height:10px;border-radius:2px;background:' +
                                                item.cor"></span>
                                            <span x-text="item.nome"></span>
                                        </span>
                                    </template>
                                    <span class="inline-flex items-center gap-1">
                                        <span
                                            style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#d1d5db"></span>
                                        Sem processo
                                    </span>
                                </div>
                                <button type="button" @click="aberta = false"
                                    class="mt-0.5 flex items-center gap-0.5 text-[9px] text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                    <x-heroicon-o-chevron-up class="w-2.5 h-2.5" />
                                    recolher
                                </button>
                            </div>

                            {{-- Sem processo em andamento --}}
                            <div x-show="processosOn && legenda.length === 0" x-transition
                                class="mt-1 text-[10px] text-gray-400 italic">
                                Nenhum processo em andamento.
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="logradouros"
                                    class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div>
                                    <span class="layer-text truncate">Logradouros</span>
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                                <label
                                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                                    title="Exibir rótulos">
                                    <input type="checkbox" id="logradouros-label-toggle" checked
                                        onchange="window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'logradouros',enabled:this.checked,field:document.getElementById('logradouros-label-field').value}}))"
                                        class="rounded border-gray-300 w-3 h-3">
                                    <span>Rót.</span>
                                </label>
                                <select id="logradouros-label-field"
                                    onchange="if(document.getElementById('logradouros-label-toggle').checked) window.dispatchEvent(new CustomEvent('sigweb-toggle-labels',{detail:{layer:'logradouros',enabled:true,field:this.value}}))"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded px-1 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                    style="font-size:10px;max-width:80px;padding:1px 2px;">
                                    <option value="__default__">Padrão</option>
                                    <option value="name">Nome</option>
                                    <option value="cep">CEP</option>
                                </select>
                            </div>
                        </div>

                        {{-- Meio-fio / Calçada (TR Tangará Intranet #57) --}}
                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="meio_fios"
                                    class="layer-toggle rounded border-gray-300 text-amber-700 focus:ring-amber-700 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-1 bg-amber-700 rounded flex-shrink-0"></div>
                                    <span class="layer-text truncate">Meio-fio / Calçada</span>
                                </span>
                            </label>
                        </div>

                        {{-- Seções de Logradouro --}}
                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="secoes_logradouro"
                                    class="layer-toggle rounded border-gray-300 text-violet-700 focus:ring-violet-700 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-1 bg-violet-700 rounded flex-shrink-0"></div>
                                    <span class="layer-text truncate">Seções de Logradouro</span>
                                </span>
                            </label>
                        </div>

                    </div>
                </div>

                {{-- GRUPO 2: INTELIGÊNCIA SOCIAL (vinculada à camada de lotes) --}}
                <div data-permission-group="layer:lotes"
                    class="border-b border-gray-100/50 dark:border-gray-700/50 bg-rose-50/30 dark:bg-rose-900/10">
                    <button @click="activeTab = activeTab === 'social' ? '' : 'social'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-rose-700 dark:text-rose-300 hover:bg-rose-100/50 dark:hover:bg-rose-800/50 flex justify-between items-center transition-colors">
                        <span class="flex items-center gap-2">Inteligência Social</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'social' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'social'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full overflow-hidden">

                        <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-2 font-bold mt-2">Mapa de
                            Vulnerabilidade (Lotes)</div>

                        {{-- FILTRO: ÁREA DE RISCO --}}
                        <label class="flex items-center space-x-3 cursor-pointer w-full group">
                            <input type="checkbox" id="filtro-social-risco"
                                class="rounded border-gray-300 text-rose-600 focus:ring-rose-500 w-4 h-4 flex-shrink-0 transition-all">
                            <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10 group-hover:animate-pulse"
                                    style="background-color: #e11d48;"></div>
                                <span class="layer-text truncate font-bold text-gray-700 dark:text-gray-300">Famílias
                                    em
                                    Área de Risco</span>
                            </span>
                        </label>

                        {{-- FILTRO: BENEFÍCIOS --}}
                        <label class="flex items-center space-x-3 cursor-pointer w-full group">
                            <input type="checkbox" id="filtro-social-beneficio"
                                class="rounded border-gray-300 text-amber-500 focus:ring-amber-500 w-4 h-4 flex-shrink-0 transition-all">
                            <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10"
                                    style="background-color: #f59e0b;"></div>
                                <span class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">Recebem
                                    Benefícios</span>
                            </span>
                        </label>

                        {{-- FILTRO: PCD --}}
                        <label class="flex items-center space-x-3 cursor-pointer w-full group">
                            <input type="checkbox" id="filtro-social-pcd"
                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 w-4 h-4 flex-shrink-0 transition-all">
                            <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10"
                                    style="background-color: #9333ea;"></div>
                                <span class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">Membros
                                    com
                                    Deficiência (PCD)</span>
                            </span>
                        </label>

                        {{-- TOGGLE: MODO MAPA DE CALOR --}}
                        <div class="mt-3 pt-3 border-t border-rose-200/50 dark:border-rose-700/30">
                            <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-2 font-bold">Modo de
                                Visualização</div>
                            <label class="flex items-center space-x-3 cursor-pointer w-full group">
                                <input type="checkbox" id="toggle-modo-heatmap"
                                    class="rounded border-gray-300 text-rose-600 focus:ring-rose-500 w-4 h-4 flex-shrink-0 transition-all">
                                <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm"
                                        style="background: radial-gradient(circle, #ef4444, #f59e0b, #3b82f6);"></div>
                                    <span class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">
                                        Mapa de Calor (Hotmap)
                                    </span>
                                </span>
                            </label>
                            <p class="text-[10px] text-gray-400 mt-1 ps-7">Ativa a visualização por densidade. Os
                                filtros acima definem quais dados são aquecidos.</p>
                        </div>

                    </div>
                </div>

                {{-- GRUPO 3: ZONEAMENTO URBANO --}}
                <div data-permission-group="layer:zonas" class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'zonas' ? '' : 'zonas'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Zoneamento Urbano</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'zonas' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'zonas'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full overflow-hidden"
                        x-data="{ zonasList: @entangle('zonasTipos') }"> {{-- MÁGICA: Conecta ao PHP! --}}

                        <template x-for="zona in zonasList" :key="zona.id">
                            <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full"
                                :title="zona.name">
                                <input type="checkbox" data-layer="zonas" :data-zona-sigla="zona.sigla"
                                    class="zona-toggle rounded border-gray-400 shadow-sm w-4 h-4 flex-shrink-0"
                                    :style="`color: rgb(${ (zona.rgb || '150,150,150').replace(/rgb|\(|\)| /g, '') });`">
                                <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-2">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10"
                                        :style="`background-color: rgb(${ (zona.rgb || '150,150,150').replace(/rgb|\(|\)| /g, '') });`">
                                    </div>
                                    <span class="layer-text truncate font-medium text-gray-700 dark:text-gray-300"
                                        x-text="`${zona.sigla} - ${zona.name}`"></span>
                                </span>
                            </label>
                        </template>

                    </div>
                </div>

                {{-- GRUPO 4: INFRAESTRUTURA --}}
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
                            <input type="checkbox" data-layer="patrimonio_publicos"
                                class="layer-toggle rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-indigo-500 rounded-sm flex-shrink-0 opacity-80"></div>
                                <span class="layer-text truncate">Patrimônio Público</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                            <input type="checkbox" data-layer="arvores"
                                class="layer-toggle rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full flex-shrink-0"></div><span
                                    class="layer-text truncate">Arborização Urbana</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                            <input type="checkbox" data-layer="postes"
                                class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div><span
                                    class="layer-text truncate">Iluminação Pública</span>
                            </span>
                        </label>

                        {{-- Áreas REURB --}}
                        <div data-permission-group="layer:areas_reurb" x-data="{ on: false }">

                            <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                                <input type="checkbox" data-layer="areas_reurb" x-model="on"
                                    class="layer-toggle rounded border-gray-300 text-amber-500 focus:ring-amber-400 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div class="w-3 h-3 bg-amber-500 rounded-sm flex-shrink-0 opacity-80"></div>
                                    <span class="layer-text truncate">Áreas REURB</span>
                                </span>
                            </label>

                            <div x-show="on" x-transition
                                class="ml-7 mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1">
                                    <span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#f59e0b"></span>
                                    Reurb-S
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#8b5cf6"></span>
                                    Reurb-E
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span
                                        style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#6b7280"></span>
                                    Sem Classif.
                                </span>
                            </div>
                        </div>

                        {{-- CAMADA: IMAGENS 360º --}}
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

                {{-- GRUPO 6: CEMITÉRIOS --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'cemiterios' ? '' : 'cemiterios'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Gestão de Cemitérios</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'cemiterios' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'cemiterios'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="cemiterios"
                                class="layer-toggle rounded border-gray-300 text-purple-600 focus:ring-purple-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-purple-600 rounded-sm opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Cemitérios</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="quadras_cemiterio"
                                class="layer-toggle rounded border-gray-300 text-indigo-500 focus:ring-indigo-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-indigo-500 rounded-sm opacity-60 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Quadras (Cemitério)</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="logradouros_cemiterio"
                                class="layer-toggle rounded border-gray-300 text-slate-500 focus:ring-slate-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-500 rounded-sm opacity-80 flex-shrink-0"></div>
                                <span class="layer-text truncate">Ruas (Cemitério)</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="jazigos"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Jazigos / Túmulos</span>
                            </span>
                        </label>

                    </div>
                </div>

                {{-- GRUPO 7: ZONA RURAL --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'rural' ? '' : 'rural'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 flex justify-between items-center">
                        <span class="flex items-center gap-2">Cadastro Rural</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'rural' ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="activeTab === 'rural'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="rural-localidades"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Localidades e Distritos</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="rural-propriedades"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Propriedades (INCRA/CAR)</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="rural-estradas"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Estradas e Vicinais</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="rural-hidrografias"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Rios, Lagos e Nascentes</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="rural-pontes"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Pontes Rurais</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="rural-pontos-interesse"
                                class="layer-toggle rounded border-gray-300 text-stone-600 focus:ring-stone-600 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-stone-600 rounded-sm opacity-70 flex-shrink-0"></div>
                                <span class="layer-text truncate">Pontos de Interesse</span>
                            </span>
                        </label>

                    </div>
                </div>

                {{-- GRUPO: ANOTAÇÕES DO MAPA --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'anotacoes' ? '' : 'anotacoes'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-violet-700 dark:text-violet-300 hover:bg-violet-50/50 dark:hover:bg-violet-900/20 flex justify-between items-center">
                        <span class="flex items-center gap-2">Anotações</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'anotacoes' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'anotacoes'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm overflow-hidden">

                        <div class="flex items-center justify-between w-full mt-2">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" data-layer="toponimias"
                                    class="layer-toggle rounded border-gray-300 text-violet-600 focus:ring-violet-500 w-4 h-4 flex-shrink-0">
                                <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                    <div
                                        class="w-3 h-3 bg-violet-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                    </div>
                                    <span class="layer-text truncate">Toponímias / Textos</span>
                                </span>
                            </label>
                            <button type="button" onclick="window.ativarFerramentaToponimiia(true)"
                                title="Clique no mapa para adicionar um texto"
                                class="ml-2 flex-shrink-0 text-xs px-2 py-0.5 rounded bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 hover:bg-violet-200 transition-colors">
                                + Texto
                            </button>
                        </div>

                    </div>
                </div>

                {{-- GRUPO OGC: CONEXÕES EXTERNAS (WMS) --}}
                <div data-permission-group="toolbar:ferramentas"
                    class="border-b border-gray-100/50 dark:border-gray-700/50">
                    <button @click="activeTab = activeTab === 'ogc' ? '' : 'ogc'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100/50 dark:hover:bg-slate-800/50 flex justify-between items-center transition-colors">
                        <span class="flex items-center gap-2">WMS Externo (OGC)</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'ogc' ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="activeTab === 'ogc'" x-collapse
                        class="px-4 pb-4 bg-transparent w-full overflow-hidden">
                        <div
                            class="p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/50 rounded-lg shadow-sm space-y-3">
                            <p class="text-[10px] text-emerald-800 dark:text-emerald-300 font-medium leading-tight">
                                Conecte serviços oficiais (IBGE, INPE) colando a URL WMS abaixo.
                            </p>

                            <div class="space-y-2">
                                <input type="text" id="wms-url" placeholder="URL do Serviço WMS..."
                                    class="w-full text-xs rounded border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400">

                                <input type="text" id="wms-layer" placeholder="Nome da Camada (Layer)"
                                    class="w-full text-xs rounded border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400">

                                <button id="btn-add-wms"
                                    style="width: 100%; background-color: #073bc0; color: white; font-weight: bold; padding: 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    + Conectar Camada OGC
                                </button>

                            </div>
                        </div>

                        {{-- Lista de camadas conectadas --}}
                        <div id="wms-layers-list" class="space-y-2 mt-3 empty:hidden">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- 🛠️ BARRA INFERIOR: VETORIZAÇÃO AVANÇADA (CAD) --}}
        <div x-data="{ openCad: false, ferramentaCadAtiva: null, ortogonalAtiva: false }"
            @toggle-cad-avancado.window="openCad = !openCad; if(!openCad) ferramentaCadAtiva = null;"
            @fechar-submenus-cad.window="ferramentaCadAtiva = null" x-show="openCad"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-10"
            style="display: none; position: fixed !important; bottom: 15px !important; left: 50% !important; transform: translateX(-50%) !important; z-index: 9998 !important;"
            class="bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-lg rounded-2xl border border-gray-200 dark:border-gray-700 px-3 py-1.5 flex items-center gap-2 pointer-events-auto transition-all">


            {{-- Ferramentas --}}
            <div class="flex items-center gap-1">
                {{-- 1. CLONAR --}}
                <button
                    @click="ferramentaCadAtiva = (ferramentaCadAtiva === 'clonar' ? null : 'clonar'); window.setFerramentaCAD(ferramentaCadAtiva);"
                    :class="ferramentaCadAtiva === 'clonar' ?
                        'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700' :
                        'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400'"
                    class="w-14 h-11 me-4 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all">
                    <x-heroicon-o-document-duplicate class="w-4 h-4" />
                    <span class="text-[9px] font-bold">Clonar</span>
                </button>

                {{-- 2. BUFFER (Botão + Input Dinâmico) --}}
                <div class="flex items-center gap-1 transition-all me-4"
                    :class="ferramentaCadAtiva === 'buffer' ?
                        'bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-700 rounded-xl pr-2' :
                        ''">
                    <button
                        @click="ferramentaCadAtiva = (ferramentaCadAtiva === 'buffer' ? null : 'buffer'); window.setFerramentaCAD(ferramentaCadAtiva);"
                        :class="ferramentaCadAtiva === 'buffer' ? 'text-indigo-600 dark:text-indigo-400' :
                            'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400 me-2'"
                        class="w-14 h-11 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all">
                        <x-heroicon-o-arrows-pointing-out class="w-4 h-4" />
                        <span class="text-[9px] font-bold">Buffer</span>
                    </button>

                    {{-- O Campinho de Metros --}}
                    <div x-show="ferramentaCadAtiva === 'buffer'" style="display: none;"
                        class="flex items-center gap-1 animate-fade-in-right">
                        <input type="text" id="input-cad-buffer" value="5.5"
                            class="w-10 h-7 px-1 text-center text-xs font-bold text-indigo-700 bg-white border border-indigo-300 rounded-lg focus:ring-0 focus:border-indigo-500"
                            title="Distância em metros (Use ponto ou vírgula)">
                        <span class="text-[10px] font-bold text-indigo-400">m</span>
                    </div>
                </div>

                {{-- 3. UNIR --}}
                <button
                    @click="ferramentaCadAtiva = (ferramentaCadAtiva === 'unir' ? null : 'unir'); window.setFerramentaCAD(ferramentaCadAtiva);"
                    :class="ferramentaCadAtiva === 'unir' ?
                        'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700' :
                        'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400'"
                    class="w-14 h-11 me-4 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all">
                    <x-heroicon-o-link class="w-4 h-4" />
                    <span class="text-[9px] font-bold">Unir</span>
                </button>

                {{-- 4. DESMEMBRAR --}}
                <button
                    @click="ferramentaCadAtiva = (ferramentaCadAtiva === 'desmembrar' ? null : 'desmembrar'); window.setFerramentaCAD(ferramentaCadAtiva);"
                    :class="ferramentaCadAtiva === 'desmembrar' ?
                        'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700' :
                        'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400'"
                    class="w-14 h-11 me-4 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all">
                    <x-heroicon-o-scissors class="w-4 h-4" />
                    <span class="text-[9px] font-bold">Cortar</span>
                </button>

                {{-- 5. LINHA ORTOGONAL --}}
                <button @click="ortogonalAtiva = !ortogonalAtiva; window.toggleOrtogonal(ortogonalAtiva);"
                    :class="ortogonalAtiva ?
                        'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700' :
                        'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400'"
                    class="w-16 h-11 me-4 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all relative">
                    <span x-show="ortogonalAtiva" style="display: none;"
                        class="absolute top-1 right-1 flex h-1.5 w-1.5">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                    </span>
                    <x-heroicon-o-bars-4 class="w-4 h-4" />
                    <span class="text-[9px] font-bold">Ortogonal</span>
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 me-4"></div>

                {{-- 6. COTAR (GABARITO / AUTO-MEDIDA) --}}
                <button
                    @click="ferramentaCadAtiva = (ferramentaCadAtiva === 'cotar' ? null : 'cotar'); window.setFerramentaCAD(ferramentaCadAtiva);"
                    :class="ferramentaCadAtiva === 'cotar' ?
                        'bg-orange-50 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-700' :
                        'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400'"
                    class="w-14 h-11 me-4 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all">
                    <x-heroicon-o-arrows-right-left class="w-4 h-4" />
                    <span class="text-[9px] font-bold">Cotar</span>
                </button>

                {{-- 7. ESPELHAR --}}
                <button
                    @click="ferramentaCadAtiva = (ferramentaCadAtiva === 'espelhar' ? null : 'espelhar'); window.setFerramentaCAD(ferramentaCadAtiva);"
                    :class="ferramentaCadAtiva === 'espelhar' ?
                        'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700' :
                        'hover:bg-gray-100 dark:hover:bg-gray-700 border border-transparent text-gray-500 dark:text-gray-400'"
                    class="w-14 h-11 rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all">
                    {{-- Ícone de espelho: duas setas opostas + linha central --}}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25 3 12l4.5 3.75M16.5 8.25 21 12l-4.5 3.75M12 3v18"/>
                    </svg>
                    <span class="text-[9px] font-bold">Espelhar</span>
                </button>

            </div>

            {{-- Botão Fechar Barra --}}
            <button @click="openCad = false; ferramentaCadAtiva = null; window.setFerramentaCAD(null);"
                class="ml-1 p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
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

                    @if ($loteStatusCadastro)
                        @php
                            $statusMap = [
                                'nao_visitado' => [
                                    'label' => 'Não Visitado',
                                    'bg' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                    'dot' => 'bg-gray-400',
                                ],
                                'coletado' => [
                                    'label' => 'Coletado',
                                    'bg' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
                                    'dot' => 'bg-emerald-500',
                                ],
                                'pendente' => [
                                    'label' => 'Pendente',
                                    'bg' => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
                                    'dot' => 'bg-amber-500',
                                ],
                                'inconformidade' => [
                                    'label' => 'Inconformidade',
                                    'bg' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                    'dot' => 'bg-red-500',
                                ],
                            ];
                            $s = $statusMap[$loteStatusCadastro] ?? $statusMap['nao_visitado'];
                        @endphp
                        <span
                            class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $s['bg'] }}">
                            <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span> {{ $s['label'] }}
                        </span>
                    @endif

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

                    {{-- COMPARATIVO CADASTRAL (aparece somente quando há dados tributários) --}}
                    @if ($loteAreaCadastrada > 0)
                        @php
                            $deltaLote =
                                $loteAreaGeo > 0
                                    ? (($loteAreaGeo - $loteAreaCadastrada) / $loteAreaCadastrada) * 100
                                    : 0;
                            $corDeltaLote =
                                abs($deltaLote) > 5
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-green-600 dark:text-green-400';
                            $iconeDeltaLote =
                                abs($deltaLote) > 5 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle';
                        @endphp
                        <div class="mt-3 pt-3 border-t border-dashed border-gray-300 dark:border-gray-600">
                            <p class="text-[10px] text-gray-400 uppercase font-bold mb-2 flex items-center gap-1">
                                <x-heroicon-o-scale class="w-3 h-3" /> Comparativo Cadastral
                            </p>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Área Cadastro:</span>
                                <span class="font-bold text-gray-700 dark:text-gray-300">
                                    {{ number_format($loteAreaCadastrada, 2, ',', '.') }} m²
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">Δ Lote:</span>
                                <span class="font-bold flex items-center gap-1 {{ $corDeltaLote }}">
                                    <x-dynamic-component :component="$iconeDeltaLote" class="w-3 h-3 flex-shrink-0" />
                                    {{ ($deltaLote >= 0 ? '+' : '') . number_format($deltaLote, 1, ',', '.') }}%
                                </span>
                                @if ($loteEdifCadastrada > 0)
                                    @php
                                        $deltaEdif =
                                            $loteAreaConstruida > 0
                                                ? (($loteAreaConstruida - $loteEdifCadastrada) / $loteEdifCadastrada) *
                                                    100
                                                : -100;
                                        $corDeltaEdif =
                                            abs($deltaEdif) > 5
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-green-600 dark:text-green-400';
                                        $iconeDeltaEdif =
                                            abs($deltaEdif) > 5
                                                ? 'heroicon-o-exclamation-triangle'
                                                : 'heroicon-o-check-circle';
                                    @endphp
                                    <span class="text-gray-500 dark:text-gray-400">Edif. Cadastro:</span>
                                    <span class="font-bold text-gray-700 dark:text-gray-300">
                                        {{ number_format($loteEdifCadastrada, 2, ',', '.') }} m²
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400">Δ Edificação:</span>
                                    <span class="font-bold flex items-center gap-1 {{ $corDeltaEdif }}">
                                        <x-dynamic-component :component="$iconeDeltaEdif" class="w-3 h-3 flex-shrink-0" />
                                        {{ ($deltaEdif >= 0 ? '+' : '') . number_format($deltaEdif, 1, ',', '.') }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($loteOcupacao || $loteSituacaoQuadra)
                        @php
                            $ocupacaoLabel = ['baldio' => 'Baldio', 'construido' => 'Construído'];
                            $situacaoLabel = [
                                'meio_quadra' => 'Meio de Quadra',
                                'esquina' => 'Esquina',
                                'encravado' => 'Encravado',
                            ];
                        @endphp
                        <div
                            class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600 grid grid-cols-2 gap-4 text-xs">
                            @if ($loteOcupacao)
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold">Ocupação</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-300">
                                        {{ $ocupacaoLabel[$loteOcupacao] ?? '—' }}</p>
                                </div>
                            @endif
                            @if ($loteSituacaoQuadra)
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold">Situação</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-300">
                                        {{ $situacaoLabel[$loteSituacaoQuadra] ?? '—' }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>

                @if ($loteColetadoPor)
                    <div class="mb-4 -mt-2 text-xs text-gray-500 italic flex items-center gap-1.5">
                        <x-heroicon-o-user-circle class="w-3.5 h-3.5 text-gray-400" />
                        Coletado por <strong
                            class="text-gray-700 dark:text-gray-300 not-italic">{{ $loteColetadoPor }}</strong>
                        @if ($loteColetadoEm)
                            em {{ $loteColetadoEm }}
                        @endif
                    </div>
                @endif

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
                        <button onclick="enableDrawing('edificacao')" title="Desenhar Nova Edificação"
                            class="flex-shrink-0 flex items-center justify-center w-[50px] h-[50px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-emerald-50 hover:border-emerald-500 hover:text-emerald-600 transition-all">
                            <x-heroicon-o-plus class="w-5 h-5" />
                        </button>
                    </div>

                    {{-- VER TESTADAS --}}
                    <div class="flex items-center gap-2 w-full">
                        <button wire:click="toggleTestadasLote"
                            style="{{ $mostrarTestadasLoteAtivo ? 'background-color: #f0fdf4; border-color: #16a34a;' : '' }}"
                            class="flex-1 flex items-center justify-between px-4 py-3 border rounded-xl transition-all group bg-white border-gray-200 hover:border-green-600 dark:bg-gray-800 dark:border-gray-700">
                            <span
                                class="text-sm font-medium flex items-center gap-2 {{ $mostrarTestadasLoteAtivo ? 'text-green-700 dark:text-green-400' : 'text-gray-700 dark:text-gray-200 group-hover:text-green-700' }}">
                                <x-heroicon-o-arrows-right-left class="w-4 h-4" />
                                {{ $mostrarTestadasLoteAtivo ? 'Ocultar Testadas' : 'Ver Testadas' }}
                            </span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <div class="w-9 h-5 rounded-full transition-colors"
                                    style="{{ $mostrarTestadasLoteAtivo ? 'background-color: #16a34a;' : 'background-color: #e5e7eb;' }}">
                                </div>
                                <div class="absolute left-[2px] top-[2px] bg-white border border-gray-300 rounded-full h-4 w-4 transition-transform"
                                    style="{{ $mostrarTestadasLoteAtivo ? 'transform: translateX(100%); border-color: white;' : '' }}">
                                </div>
                            </div>
                        </button>
                        <button onclick="enableDrawing('testada')" title="Desenhar Nova Testada"
                            class="flex-shrink-0 flex items-center justify-center w-[50px] h-[50px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-green-50 hover:border-green-600 hover:text-green-700 transition-all">
                            <x-heroicon-o-plus class="w-5 h-5" />
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

                    {{-- BOTÃO DE PROCESSOS EM ABERTO (visível apenas quando há processo vinculado ao lote) --}}
                    @if (count($loteProcessosAbertos) > 0)
                        <button wire:click="mountAction('verProcessosLote')"
                            class="w-full text-left px-4 py-3 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-700 rounded-xl hover:border-indigo-500 hover:shadow-md transition-all group flex items-center justify-between">
                            <span
                                class="text-sm font-medium text-indigo-700 dark:text-indigo-300 group-hover:text-indigo-600 flex items-center gap-2">
                                <x-heroicon-o-document-check class="w-4 h-4 flex-shrink-0" />
                                Processos digitais
                                <span
                                    class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold bg-indigo-600 text-white rounded-full">{{ count($loteProcessosAbertos) }}</span>
                            </span>
                            <x-heroicon-o-arrow-right
                                class="w-4 h-4 text-indigo-400 group-hover:text-indigo-500 flex-shrink-0" />
                        </button>
                    @endif

                    {{-- 👈 BOTÃO: GERENCIAR AS 3 FOTOS DO LOTE (frontal + 2 laterais) --}}
                    <button type="button" wire:click="mountAction('gerenciarFotosLote')"
                        style="width: 100%; margin-top: 10px; background-color: #4b5563; color: white; font-weight: bold; padding: 10px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; border: none; cursor: pointer;">
                        <x-heroicon-o-camera class="w-5 h-5" />
                        Fotos do Lote
                    </button>

                </div>
            @endif
        </div>

        @if ($loteAtivoId)
            <div
                class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-row gap-2">

                {{-- DESMEMBRAR LOTE --}}
                <button onclick="ativarFerramentaCorteLote({{ $loteAtivoId }})" title="Desmembrar / Cortar Lote"
                    class="flex-1 flex justify-center py-2.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-xl font-bold border border-blue-100 transition-colors">
                    <x-heroicon-o-scissors class="w-5 h-5" />
                </button>

                {{-- STREET VIEW LOTE --}}
                <button wire:click="mountAction('abrirStreetViewAction')" title="Explorar Street View"
                    class="flex-1 flex justify-center py-2.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-xl font-bold border border-blue-100 transition-colors">
                    <x-heroicon-o-globe-americas class="w-5 h-5" />
                </button>

                {{-- EDITAR LOTE --}}
                <button wire:click="mountAction('editarDadosLote')" title="Editar Dados"
                    class="flex-1 flex justify-center py-2.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-xl font-bold border border-blue-100"><x-heroicon-o-document-text
                        class="w-5 h-5" />
                </button>
                {{-- GEOMETRIA LOTE --}}
                <button wire:click="habilitarEdicaoGeometria" title="Editar Geometria"
                    class="flex-1 flex justify-center py-2.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 rounded-xl font-bold border border-emerald-100"><x-heroicon-o-map
                        class="w-5 h-5" />
                </button>
                {{-- EXCLUIR LOTE --}}
                <button wire:click="mountAction('deletarArtefato')" title="Excluir Lote"
                    class="flex-1 flex justify-center py-2.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl font-bold border border-red-100"><x-heroicon-o-trash
                        class="w-5 h-5" />
                </button>
            </div>
        @endif
    </div>

    {{-- MOTOR MATEMÁTICO ESPACIAL (POSTGIS DE BOLSO) --}}
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

    {{-- PROJ4 — transformações de coordenadas (usado pelo card de coords UTM SIRGAS 2000) --}}
    <script src="https://cdn.jsdelivr.net/npm/proj4@2.10.0/dist/proj4.min.js"></script>

    {{-- SCRIPTS (Apenas o Alpine.js de Busca de UI ficou aqui) --}}
    <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
    <script src="{{ asset('js/gis/mapa-engine.js') }}"></script>

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
                    // Lendo a variável config limpa que passamos lá em cima
                    fetch(`/api/search-lote?tenant_id=${window.mapConfig.tenantId}&termo=${this.termo}`)
                        .then(res => res.json())
                        .then(data => {
                            this.resultados = data;
                        })
                        .catch(() => {
                            this.resultados = [];
                        })
                        .finally(() => {
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
    </script>

    <script>
        // Ouvinte que escuta o Livewire pedindo o PDF do Parcelamento
        window.addEventListener('capturar-mapa-parcelamento', (e) => {
            const data = e.detail[0] || e.detail;
            // Chama a função JS passando os parâmetros certinhos
            window.capturarMapaParcelamento(data.lote_id, data.qtd_lotes);
        });

        // Ouvinte para Planta de Quadra (TR Tangará Intranet #16)
        window.addEventListener('capturar-mapa-planta-quadra', (e) => {
            const data = e.detail[0] || e.detail;
            window.capturarMapaPlantaQuadra(data.id);
        });

        /* PLANTA DE QUADRA — captura mapa centrado na quadra com a quadra destacada */
        window.capturarMapaPlantaQuadra = function(quadraId) {
            // Destaca a quadra no mapa
            let featureToHighlight = null;
            if (window.loadedLayers && window.loadedLayers['quadras']) {
                const source = window.loadedLayers['quadras'].getSource();
                featureToHighlight = source.getFeatures().find(f => f.get('id') == quadraId);
                if (featureToHighlight) {
                    featureToHighlight.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#1d4ed8',
                            width: 4
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(59, 130, 246, 0.15)'
                        })
                    }));

                    // Zoom para enquadrar a quadra
                    try {
                        const extent = featureToHighlight.getGeometry().getExtent();
                        if (window.map && extent) {
                            window.map.getView().fit(extent, {
                                padding: [80, 80, 80, 80],
                                duration: 600,
                                maxZoom: 19
                            });
                        }
                    } catch (e) {
                        /* segue mesmo se o fit falhar */
                    }
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

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.85);
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirPlantaQuadra(quadraId, dataURL);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para Planta de Quadra:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    if (featureToHighlight) {
                        featureToHighlight.setStyle(undefined);
                    }
                }
            }, 1200); // delay maior para garantir que o fit/zoom completou
        };

        /* IMPRESSÃO ÚNICA COM DESTAQUE */
        window.capturarMapaEImprimirBic = function(unidadeId, loteId) {
            // 🛑 MÁGICA 1: Encontrar a feature do lote via loadedLayers (como na Viabilidade)
            let featureToHighlight = null;
            if (window.loadedLayers && window.loadedLayers['lotes']) {
                const source = window.loadedLayers['lotes'].getSource();
                featureToHighlight = source.getFeatures().find(f => f.get('id') == loteId);

                if (featureToHighlight) {
                    featureToHighlight.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff0000',
                            width: 4
                        }), // Borda vermelha
                        fill: new ol.style.Fill({
                            color: 'rgba(250, 204, 21, 0.5)'
                        }) // Fundo amarelo
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
                            .imprimirBic(unidadeId, dataURL);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para BIC:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    // 🛑 MÁGICA 2: Remove a tinta amarela do lote após capturar
                    if (featureToHighlight) {
                        featureToHighlight.setStyle(undefined);
                    }
                }
            }, 800); // 800ms de delay como na Viabilidade
        };

        /* IMPRESSÃO EM MASSA COM DESTAQUE */
        window.capturarMapaEImprimirBicEmMassa = function(arrayDeIds, loteId) {
            const idsPuros = Array.from(arrayDeIds);

            // 🛑 MÁGICA 1: Destaque
            let featureToHighlight = null;
            if (window.loadedLayers && window.loadedLayers['lotes']) {
                const source = window.loadedLayers['lotes'].getSource();
                featureToHighlight = source.getFeatures().find(f => f.get('id') == loteId);

                if (featureToHighlight) {
                    featureToHighlight.setStyle(new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff0000',
                            width: 4
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(250, 204, 21, 0.5)'
                        })
                    }));
                }
            }

            if (idsPuros.length === 0) {
                if (featureToHighlight) featureToHighlight.setStyle(undefined);
                return;
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
                            .imprimirBicEmMassa(idsPuros, dataURL);
                    } else {
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirBicEmMassa(idsPuros, null);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa em massa:", error);
                    alert("Não foi possível capturar a imagem do mapa. Gerando BICs sem imagem.");
                    Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                        .imprimirBicEmMassa(idsPuros, null);
                } finally {
                    // 🛑 MÁGICA 2: Limpa a cor
                    if (featureToHighlight) {
                        featureToHighlight.setStyle(undefined);
                    }
                }
            }, 800);
        };

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

        /* IMPRIMIR ESTUDO DE PARCELAMENTO DO LOTE (COM DESTAQUE) */
        window.capturarMapaParcelamento = function(loteId, qtdLotes) { // 🛑 Parâmetro qtdLotes em vez de cnaes!

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

                        // 🛑 CHAMA A FUNÇÃO NOVA NO PHP: imprimirParcelamento (na ordem exata que criamos na Trait)
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirParcelamento(dataURL, qtdLotes, loteId);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para o Parcelamento:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    // 🛑 MÁGICA 2: "Limpar a tinta". Devolve a cor original ao lote!
                    if (featureToHighlight) {
                        featureToHighlight.setStyle(undefined);
                    }
                }
            }, 800);
        };


        /* IMPRIMIR NUMERAÇÃO PREDIAL COM MAPA */
        window.capturarMapaNumeracao = function() {
            const btn = document.getElementById('btn-print-num');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="animate-pulse">Gerando PDF...</span>';

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

                        // Dispara a função do PHP mandando a foto em Base64
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
                            .imprimirRelatorioNumeracao(dataURL);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    btn.innerHTML = originalText;
                }
            }, 500);
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

    {{-- Carrega o Google Maps com a biblioteca de Geometria (para calcular o ângulo do olhar) --}}
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=geometry" async
        defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- Bibliotecas para Impressão e Exportação --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <x-filament-actions::modals />

    {{-- PAINEL DE ESTATÍSTICAS --}}
    <div id="painel-estatisticas"
        style="
        display:none; position:absolute; top:70px; right:16px; z-index:1000;
        background:rgba(17,24,39,0.95); backdrop-filter:blur(8px);
        border:1px solid rgba(255,255,255,0.1); border-radius:12px;
        padding:16px; width:340px; max-height:80vh; overflow-y:auto;
        box-shadow:0 4px 24px rgba(0,0,0,0.5);
    ">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span id="stat-titulo" style="font-size:13px;font-weight:600;color:#f9fafb;">Estatísticas</span>
            <button
                onclick="document.getElementById('painel-estatisticas').style.display='none'; window.limparOverlaysEstat();"
                style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px;line-height:1;">✕</button>
        </div>
        <div id="stat-resumo" style="font-size:11px;color:#9ca3af;margin-bottom:12px;"></div>
        <canvas id="stat-chart" style="width:100%;max-height:220px;"></canvas>
        <div id="stat-tabela" style="margin-top:12px;"></div>
    </div>

    {{-- PAINEL DE FILTROS ATIVOS --}}
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
                style="
                font-size:10px; padding:3px 8px; border-radius:6px;
                background:rgba(239,68,68,0.2); color:#f87171;
                border:1px solid rgba(239,68,68,0.3); cursor:pointer;
            ">Limpar
                Todos</button>
        </div>
        <div id="lista-filtros-ativos" style="display:flex; flex-direction:column; gap:4px;"></div>
    </div>
</div>
