<div class="relative w-screen h-screen overflow-hidden bg-gray-100 dark:bg-gray-900 font-sans text-gray-800">

    {{-- 🛡️ ÁREA PROTEGIDA DO LIVEWIRE 🛡️ --}}
    <div wire:ignore>
        {{-- Importação de Estilos (CDN + Seu CSS) --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <link rel="stylesheet" href="{{ asset('css/gis/mapa-sigweb.css') }}">

        {{-- 1. CONFIGURAÇÃO GLOBAL (Ponte PHP -> JS) --}}
        <script>
            window.mapConfig = {
                tenantId: {{ $tenantId }},
                tenantSlug: '{{ $tenantSlug }}',
                mapLat: {{ $mapLat }},
                mapLon: {{ $mapLon }},
                mapZoom: {{ $mapZoom }}
            };
        </script>

        {{-- O MAPA --}}
        <div id="sigweb-map" class="absolute inset-0 z-0 w-full h-full"></div>

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
                class="flex items-center gap-2 pointer-events-auto bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 rounded-2xl max-w-2xl w-full mx-4">

                {{-- Busca Integrada com AUTOCOMPLETE (Alpine.js) --}}
                <div x-data="loteSearch()"
                    class="relative flex items-center flex-1 min-w-[200px] border-r border-gray-100 dark:border-gray-700 px-2"
                    x-ref="inputWrapper">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 mr-2" />
                    <input type="text" x-model="termo" @input.debounce.500ms="buscar(); posicionarDropdown()"
                        @keydown.enter="buscar()" x-ref="inputField"
                        placeholder="Buscar lote, Cód Tributário ou Logradouro..."
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

                                    {{-- Ícone muda dependendo se é lote ou rua --}}
                                    <template x-if="res.tipo === 'lote'">
                                        <x-heroicon-o-map-pin class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                    </template>
                                    <template x-if="res.tipo === 'logradouro'">
                                        <x-heroicon-o-minus class="w-5 h-5 text-slate-500 flex-shrink-0 mt-0.5" />
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

                <div class="flex items-center gap-1 px-1">
                    {{-- DROPDOWN DE CRIAÇÃO --}}
                    <div x-data="{ openDraw: false }" class="relative">
                        <button @click="openDraw = !openDraw" @click.outside="openDraw = false" title="Desenhar no Mapa"
                            class="p-2 hover:bg-primary-100 dark:hover:bg-primary-900/20 rounded-xl text-gray-600 dark:text-gray-400 transition-colors focus:outline-none flex items-center gap-1">
                            <x-heroicon-o-pencil-square class="w-5 h-5" />
                            <x-heroicon-o-chevron-down class="w-3 h-3" />
                        </button>
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

                                <button onclick="enableDrawing('logradouro')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-minus class="w-4 h-4" /> Logradouro (Linha)
                                </button>

                                <button onclick="enableDrawing('poste')" @click="openDraw = false"
                                    class="w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z">
                                        </path>
                                    </svg>
                                    Poste / Ponto de Luz
                                </button>

                                <button onclick="enableDrawing('arvore')" @click="openDraw = false"
                                    class="w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-sparkles class="w-4 h-4 text-emerald-500" /> Árvores
                                </button>

                                <button onclick="enableDrawing('setor_fiscal')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-amber-50 dark:hover:bg-gray-700 hover:text-amber-600 flex items-center gap-2 ">
                                    <x-heroicon-o-currency-dollar class="w-4 h-4 text-amber-500" /> Setor Fiscal (Polígono)
                                </button>

                                {{-- Cemitério --}}
                                <button onclick="enableDrawing('cemiterio')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2 border-t border-gray-200">
                                    <x-heroicon-o-stop class="w-4 h-4 text-purple-600" /> Cemitério (Polígono)
                                </button>

                                <button onclick="enableDrawing('quadra_cemiterio')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-squares-2x2 class="w-4 h-4 text-indigo-500" /> Quadra de Cemitério
                                </button>

                                <button onclick="enableDrawing('logradouro_cemiterio')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-arrows-right-left class="w-4 h-4 text-slate-500" /> Rua de Cemitério
                                </button>

                                <button onclick="enableDrawing('jazigo')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-archive-box class="w-4 h-4 text-stone-600" /> Jazigo / Túmulo
                                </button>

                                {{-- Rural --}}
                                <button onclick="enableDrawing('rural_localidade')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2 border-t border-gray-200">
                                    <x-heroicon-o-map class="w-4 h-4 text-stone-600" /> Localidade / Distrito
                                </button>
                                
                                <button onclick="enableDrawing('rural_propriedade')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-home-modern class="w-4 h-4 text-stone-600" /> Propriedade (CAR)
                                </button>

                                <button onclick="enableDrawing('rural_estrada')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-lifebuoy class="w-4 h-4 text-stone-600" /> Estrada / Vicinal
                                </button>

                                <button onclick="enableDrawing('rural_hidro_linha')" @click="openDraw = false" class="px-4 py-2 text-sm text-left text-gray-700 hover:bg-primary-50 flex items-center gap-2">
                                    <x-heroicon-o-minus class="w-4 h-4 text-blue-500" /> Rio / Córrego (Linha)
                                </button>
                                <button onclick="enableDrawing('rural_hidro_poligono')" @click="openDraw = false" class="px-4 py-2 text-sm text-left text-gray-700 hover:bg-primary-50 flex items-center gap-2">
                                    <x-heroicon-o-stop class="w-4 h-4 text-blue-500" /> Lago / Represa (Polígono)
                                </button>
                                <button onclick="enableDrawing('rural_hidro_ponto')" @click="openDraw = false" class="px-4 py-2 text-sm text-left text-gray-700 hover:bg-primary-50 flex items-center gap-2">
                                    <x-heroicon-o-sparkles class="w-4 h-4 text-blue-500" /> Nascente (Ponto)
                                </button>

                                <button onclick="enableDrawing('rural_ponte')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-bars-2 class="w-4 h-4 text-stone-600" /> Ponte
                                </button>

                                <button onclick="enableDrawing('rural_ponto_interesse')" @click="openDraw = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-600 flex items-center gap-2">
                                    <x-heroicon-o-star class="w-4 h-4 text-stone-600" /> Ponto de Interesse
                                </button>

                            </div>
                        </div>
                    </div>

                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

                    <button id="btn-pan" title="Mover Mapa (Cancelar Ferramentas)"
                        class="p-2 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl transition-colors focus:outline-none">
                        <x-heroicon-o-hand-raised class="w-5 h-5" />
                    </button>

                    <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

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

                                <button wire:click="mountAction('abrirNuvemPontosAction')" @click="openTools = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 flex items-center gap-2 font-bold transition-colors">
                                    <x-heroicon-o-cube class="w-4 h-4 text-blue-500" /> Visualizador 3D (LiDAR)
                                </button>

                                <button wire:click="mountAction('configurarPgvAction')" @click="openTools = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-emerald-50 dark:hover:bg-gray-700 hover:text-emerald-600 flex items-center gap-2 font-bold transition-colors border-b border-gray-100 pb-3 mb-1">
                                    <x-heroicon-o-banknotes class="w-4 h-4 text-emerald-500" /> Simulador de Valores (PGV)
                                </button>

                                <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                                <button id="btn-measure-line" @click="openTools = false"
                                    class="px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors">
                                    <x-heroicon-o-arrows-right-left class="w-4 h-4 text-gray-500" /> Medir Distância
                                </button>

                                <button id="btn-measure-area" @click="openTools = false"
                                    class="w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors">
                                    <x-heroicon-o-view-columns class="w-4 h-4 text-gray-500" /> Medir Área
                                </button>
                            </div>
                        </div>
                    </div>

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

        {{-- BARRA FLUTUANTE DE EDIÇÃO ESPACIAL (Alpine.js) --}}
        <div x-data="{ editandoId: null }" x-show="editandoId !== null"
            @iniciar-edicao.window="editandoId = $event.detail.id" @encerrar-edicao.window="editandoId = null"
            style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-emerald-500 px-6 py-3 flex items-center gap-4 animate-bounce-short">
            <span class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                Editando Geometria...
            </span>
            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
            <button onclick="salvarEdicaoGeometria()"
                class="text-sm font-black text-emerald-600 hover:text-emerald-700 uppercase">
                Salvar
            </button>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button onclick="cancelarEdicaoGeometria()"
                class="text-sm font-black text-red-600 hover:text-red-700 uppercase">
                Cancelar
            </button>
        </div>

        {{-- BARRA FLUTUANTE DE REVISÃO DA NUMERAÇÃO PREDIAL (Padrão Pílula) --}}
        <div x-data="{ previewAtivo: @entangle('previewNumeracaoAtivo') }" x-show="previewAtivo"
            style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-blue-500 px-6 py-3 flex items-center gap-4 animate-bounce-short">

            <span class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                </span>
                Revisão de Numeração Predial
            </span>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button wire:click="confirmarNumeracaoAction"
                class="text-sm font-black text-blue-600 hover:text-blue-700 uppercase transition-colors">
                Salvar
            </button>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button onclick="capturarMapaNumeracao()" id="btn-print-num"
                class="text-sm font-black text-amber-600 hover:text-amber-700 uppercase transition-colors flex items-center gap-1">
                <x-heroicon-o-printer class="w-4 h-4" /> Relatório PDF
            </button>

            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

            <button wire:click="cancelarNumeracaoAction"
                class="text-sm font-black text-red-600 hover:text-red-700 uppercase transition-colors">
                Cancelar
            </button>
        </div>

        {{-- BARRA FLUTUANTE DE REVISÃO DA PGV --}}
        <div x-data="{ previewPgv: @entangle('previewPgvAtivo') }" x-show="previewPgv" style="display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999;"
            class="bg-white dark:bg-gray-800 shadow-2xl rounded-full border-2 border-emerald-500 px-6 py-3 flex items-center gap-4 animate-bounce-short">

            <span class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <span class="relative flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span></span>
                Revisão da PGV (Simulador Ativo)
            </span>
            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
            <button wire:click="homologarPgvAction" class="text-sm font-black text-emerald-600 hover:text-emerald-700 uppercase">
                Salvar & Homologar
            </button>
            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
            <button wire:click="cancelarPgvAction" class="text-sm font-black text-red-600 hover:text-red-700 uppercase">
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
                            <input type="checkbox" data-layer="perimetros"
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
                                </div><span class="layer-text truncate">Quadras</span>
                            </span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="lotes"
                                class="layer-toggle rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full opacity-60 shadow-sm flex-shrink-0">
                                </div><span class="layer-text truncate">Lotes</span>
                            </span>
                        </label>

                        {{-- <label class="flex items-center space-x-3 cursor-pointer w-full">
                            <input type="checkbox" data-layer="edificacoes"
                                class="layer-toggle rounded border-gray-300 text-amber-700 focus:ring-amber-700 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-3 bg-amber-700 rounded-sm opacity-80 shadow-sm flex-shrink-0"></div>
                                <span class="layer-text truncate">Edificações</span>
                            </span>
                        </label> --}}

                        <label class="flex items-center space-x-3 cursor-pointer mt-2 w-full">
                            <input type="checkbox" data-layer="logradouros"
                                class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-3 h-1 bg-slate-600 rounded flex-shrink-0"></div><span
                                    class="layer-text truncate">Logradouros</span>
                            </span>
                        </label>


                    </div>
                </div>

                {{-- GRUPO 2: INTELIGÊNCIA SOCIAL --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50 bg-rose-50/30 dark:bg-rose-900/10">
                    <button @click="activeTab = activeTab === 'social' ? '' : 'social'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-rose-700 dark:text-rose-300 hover:bg-rose-100/50 dark:hover:bg-rose-800/50 flex justify-between items-center transition-colors">
                        <span class="flex items-center gap-2">Inteligência Social</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'social' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'social'" x-collapse
                        class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full overflow-hidden">
                        
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-2 font-bold mt-2">Mapa de Vulnerabilidade (Lotes)</div>

                        {{-- FILTRO: ÁREA DE RISCO --}}
                        <label class="flex items-center space-x-3 cursor-pointer w-full group">
                            <input type="checkbox" id="filtro-social-risco"
                                class="rounded border-gray-300 text-rose-600 focus:ring-rose-500 w-4 h-4 flex-shrink-0 transition-all">
                            <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10 group-hover:animate-pulse" style="background-color: #e11d48;"></div>
                                <span class="layer-text truncate font-bold text-gray-700 dark:text-gray-300">Famílias em Área de Risco</span>
                            </span>
                        </label>

                        {{-- FILTRO: BENEFÍCIOS --}}
                        <label class="flex items-center space-x-3 cursor-pointer w-full group">
                            <input type="checkbox" id="filtro-social-beneficio"
                                class="rounded border-gray-300 text-amber-500 focus:ring-amber-500 w-4 h-4 flex-shrink-0 transition-all">
                            <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10" style="background-color: #f59e0b;"></div>
                                <span class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">Recebem Benefícios</span>
                            </span>
                        </label>

                        {{-- FILTRO: PCD --}}
                        <label class="flex items-center space-x-3 cursor-pointer w-full group">
                            <input type="checkbox" id="filtro-social-pcd"
                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 w-4 h-4 flex-shrink-0 transition-all">
                            <span class="layer-label flex items-center gap-2 text-xs flex-1 min-w-0 ps-1">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 opacity-80 shadow-sm border border-black/10" style="background-color: #9333ea;"></div>
                                <span class="layer-text truncate font-medium text-gray-700 dark:text-gray-300">Membros com Deficiência (PCD)</span>
                            </span>
                        </label>

                    </div>
                </div>

                {{-- GRUPO 3: ZONEAMENTO URBANO --}}
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

                    </div>
                </div>

                {{-- GRUPO 5: GESTÃO TRIBUTÁRIA --}}
                <div class="border-b border-gray-100/50 dark:border-gray-700/50 bg-amber-50/30 dark:bg-amber-900/10">
                    <button @click="activeTab = activeTab === 'pgv' ? '' : 'pgv'"
                        class="w-full px-4 py-3 text-left font-bold text-sm text-amber-700 dark:text-amber-500 hover:bg-amber-100/50 flex justify-between items-center transition-colors">
                        <span class="flex items-center gap-2">Gestão Tributária (PGV)</span>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                            x-bind:class="activeTab === 'pgv' ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="activeTab === 'pgv'" x-collapse class="px-4 pb-4 space-y-3 bg-transparent text-sm w-full">
                        <label class="flex items-center space-x-3 cursor-pointer w-full mt-2">
                            <input type="checkbox" data-layer="setores_fiscais"
                                class="layer-toggle rounded border-gray-300 text-amber-600 focus:ring-amber-500 w-4 h-4 flex-shrink-0">
                            <span class="layer-label flex items-center gap-2 flex-1">
                                <div class="w-3 h-3 bg-amber-500 rounded-sm opacity-80 flex-shrink-0"></div>
                                <span class="layer-text font-bold text-gray-700 dark:text-gray-300">Zonas de Valor (Setores Fiscais)</span>
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
            @if($loteAtivoId)
                <div class="mb-6 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-xl border border-gray-200 dark:border-gray-600">
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
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Ver
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

                    {{-- BOTÃO DE VIABILIDADE --}}
                    <button wire:click="mountAction('consultarViabilidadeAction')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">
                            Consulta de Viabilidade
                        </span>
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                    {{-- BOTÃO DE MEMORIAL DESCRITIVO --}}
                    <button wire:click="mountAction('gerarMemorialAction')"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-emerald-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-emerald-600">
                            Memorial Descritivo
                        </span>
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                    {{-- BOTÃO DE CROQUI DE LOCALIZAÇÃO --}}
                    <button onclick="capturarMapaEImprimirCroqui({{ $loteAtivoId }})"
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-blue-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-blue-600">
                            Croqui de Localização
                        </span>
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400 group-hover:text-emerald-500" />
                    </button>

                </div>
            @endif
        </div>

        @if($loteAtivoId)
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-row gap-2">
               
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
                        this.dropdownMaxHeight = Math.max(200, window.innerHeight - rect.bottom - 16);
                        this.dropdownStyle = `top: ${rect.bottom + 8}px; left: ${rect.left}px; width: 480px;`;
                    });
                },
                buscar() {
                    if (this.termo.length < 2) { this.resultados = []; return; }
                    this.loading = true;
                    this.posicionarDropdown();
                    // Lendo a variável config limpa que passamos lá em cima
                    fetch(`/api/search-lote?tenant_id=${window.mapConfig.tenantId}&termo=${this.termo}`)
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

    <script>

        // IMPRIMIR CONSULTA E VIABILIDADE COM LOTE DO MAPA
        window.capturarMapaEImprimir = function (loteId, cnaesString) {

            const btn = document.getElementById('btn-imprimir-viab');
            if (btn) btn.innerHTML = '<span class="animate-pulse">Gerando PDF...</span>';

            // 🛑 O TRUQUE 2: O Javascript pega a string e corta em um Array perfeito!
            const cnaesArray = cnaesString ? cnaesString.split(',') : [];

            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        mapCanvas.width = canvases[0].width;
                        mapCanvas.height = canvases[0].height;
                        const mapContext = mapCanvas.getContext('2d');

                        Array.prototype.forEach.call(canvases, function (canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);
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

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);

                        // Manda para o Livewire gerando o PDF com o Array correto!
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirViabilidade(dataURL, cnaesArray, loteId);
                    } else {
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirViabilidade(null, cnaesArray, loteId);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa:", error);
                    Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirViabilidade(null, cnaesArray, loteId);
                } finally {
                    if (btn) btn.innerHTML = 'Imprimir Relatório';
                }
            }, 500);
        };

        /* IMPRIMIR NUMERAÇÃO PREDIAL COM MAPA */
        window.capturarMapaNumeracao = function () {
            const btn = document.getElementById('btn-print-num');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="animate-pulse">Gerando PDF...</span>';

            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        mapCanvas.width = canvases[0].width;
                        mapCanvas.height = canvases[0].height;
                        const mapContext = mapCanvas.getContext('2d');

                        Array.prototype.forEach.call(canvases, function (canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);
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

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);

                        // Dispara a função do PHP mandando a foto em Base64
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirRelatorioNumeracao(dataURL);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    btn.innerHTML = originalText;
                }
            }, 500);
        };

        /* IMPRIMIR BIC */
        window.capturarMapaEImprimirBic = function (unidadeId) {
            // Pequeno delay para garantir que o mapa está estabilizado
            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        mapCanvas.width = canvases[0].width;
                        mapCanvas.height = canvases[0].height;
                        const mapContext = mapCanvas.getContext('2d');

                        Array.prototype.forEach.call(canvases, function (canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);
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

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);

                        // Dispara a função do Livewire mandando o ID e a foto!
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirBic(unidadeId, dataURL);
                    } else {
                        // Se não conseguir capturar o mapa por algum motivo, imprime sem foto
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirBic(unidadeId, null);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para a BIC:", error);
                    alert("Não foi possível capturar a imagem do mapa. Gerando BIC sem imagem.");
                    Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirBic(unidadeId, null);
                }
            }, 500);
        };

        /* IMPRIMIR CROQUI DE LOCALIZAÇÃO DO LOTE */
        window.capturarMapaEImprimirCroqui = function (loteId) {

            // Opcional: Feedback visual de que está carregando
            const btnSpan = event.currentTarget.querySelector('span');
            const originalText = btnSpan.innerText;
            btnSpan.innerHTML = '<span class="animate-pulse">Gerando Croqui...</span>';

            setTimeout(() => {
                try {
                    const mapCanvas = document.createElement('canvas');
                    const canvases = document.querySelectorAll('.ol-layer canvas');

                    if (canvases.length > 0) {
                        mapCanvas.width = canvases[0].width;
                        mapCanvas.height = canvases[0].height;
                        const mapContext = mapCanvas.getContext('2d');

                        Array.prototype.forEach.call(canvases, function (canvas) {
                            if (canvas.width > 0) {
                                const opacity = canvas.parentNode.style.opacity;
                                mapContext.globalAlpha = opacity === '' ? 1 : Number(opacity);
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

                        const dataURL = mapCanvas.toDataURL('image/jpeg', 0.8);

                        // Manda para o Livewire
                        Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).imprimirCroqui(loteId, dataURL);
                    }
                } catch (error) {
                    console.error("Erro na captura do mapa para o Croqui:", error);
                    alert("Não foi possível capturar a imagem do mapa.");
                } finally {
                    // Restaura o texto original do botão
                    btnSpan.innerText = originalText;
                }
            }, 500);
        };

    </script>

    {{-- Carrega o Google Maps com a biblioteca de Geometria (para calcular o ângulo do olhar) --}}
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=geometry" async
        defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <x-filament-actions::modals />
</div>