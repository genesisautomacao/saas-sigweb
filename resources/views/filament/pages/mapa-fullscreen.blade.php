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
            }
        </style>

        {{-- O MAPA --}}
        <div id="sigweb-map" class="absolute inset-0 z-0 w-full h-full"></div>

        {{-- BARRA SUPERIOR E CONTROLES --}}
        <div class="absolute top-4 left-0 w-full px-4 z-40 pointer-events-none flex items-start justify-between">

            <div class="pointer-events-auto">
                <a href="/app/{{ $tenantSlug }}"
                    class="bg-white/95 dark:bg-gray-800/95 shadow-lg border border-gray-200 dark:border-gray-700 px-4 py-2.5 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition-all flex items-center gap-2">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                    <span class="hidden sm:inline">Painel</span>
                </a>
            </div>

            <div
                class="flex items-center gap-2 pointer-events-auto bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 rounded-2xl max-w-2xl w-full mx-4">
                {{-- Busca Integrada --}}
                <div class="flex items-center flex-1 min-w-[200px] border-r border-gray-100 dark:border-gray-700 px-2">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 mr-2" />
                    <input type="text" id="input-busca-lote" placeholder="Buscar lote (ex: 125)..."
                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-gray-700 dark:text-gray-200 outline-none">
                </div>

                <div class="flex items-center gap-1 px-1">
                    <button id="btn-toggle-layers"
                        class="px-4 py-2 bg-primary-50 dark:bg-primary-900/20 hover:bg-primary-100 rounded-xl text-primary-600 dark:text-primary-400 font-bold text-sm flex items-center gap-2">
                        <x-heroicon-o-square-3-stack-3d class="w-5 h-5" />
                        <span class="hidden md:inline">Camadas</span>
                    </button>
                </div>
            </div>
            <div class="w-10"></div>
        </div>

        {{-- JANELA DE CAMADAS --}}
        <div id="layers-panel" style="top: 80px; right: 20px;"
            class="absolute bg-white/98 dark:bg-gray-800/98 backdrop-blur-md shadow-2xl rounded-2xl border border-gray-200 dark:border-gray-700 w-72 flex flex-col overflow-hidden z-20 pointer-events-auto">
            <div class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex justify-between items-center cursor-grab active:cursor-grabbing"
                id="layers-panel-header">
                <h3 class="font-bold text-sm text-gray-700 flex items-center gap-2 uppercase">
                    <x-heroicon-o-bars-3 class="w-4 h-4" /> Camadas
                </h3>
            </div>
            <div class="p-4 space-y-4 text-sm text-gray-700 dark:text-gray-300 max-h-[70vh] overflow-y-auto">
                {{-- INSIRA SEUS CHECKBOXES AQUI EXATAMENTE COMO ESTAVAM --}}
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" checked data-layer="perimetros"
                        class="layer-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-3 bg-red-500 rounded-full opacity-50"></div> Perímetros
                    </span>
                </label>
                {{-- Adicione os outros checkboxes abaixo... --}}
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" data-layer="zonas"
                        class="layer-toggle rounded border-gray-300 text-purple-600 focus:ring-purple-500 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-3 bg-purple-500 rounded-full opacity-50"></div> Zonas
                    </span>
                </label>
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" data-layer="bairros"
                        class="layer-toggle rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full opacity-50"></div> Bairros
                    </span>
                </label>
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" data-layer="quadras"
                        class="layer-toggle rounded border-gray-300 text-orange-500 focus:ring-orange-500 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-3 bg-orange-500 rounded-full opacity-50"></div> Quadras
                    </span>
                </label>

                <hr class="border-gray-200 dark:border-gray-700 my-2">

                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" data-layer="logradouros"
                        class="layer-toggle rounded border-gray-300 text-slate-600 focus:ring-slate-500 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-1 bg-slate-600"></div> Logradouros
                    </span>
                </label>
                <label class="flex items-center space-x-3 cursor-pointer" title="Visível apenas de perto">
                    <input type="checkbox" data-layer="lotes"
                        class="layer-toggle rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-3 bg-emerald-500 rounded-full opacity-50"></div> Lotes <small
                            class="text-xs text-gray-400">(Zoom)</small>
                    </span>
                </label>
                <label class="flex items-center space-x-3 cursor-pointer" title="Visível apenas de perto">
                    <input type="checkbox" data-layer="edificacoes"
                        class="layer-toggle rounded border-gray-300 text-amber-700 focus:ring-amber-700 shadow-sm w-4 h-4">
                    <span class="layer-label flex items-center gap-2">
                        <div class="w-3 h-3 bg-amber-700 rounded-sm opacity-80"></div> Edificações <small
                            class="text-xs text-gray-400">(Zoom)</small>
                    </span>
                </label>
            </div>
        </div>
    </div>

    {{-- ⚡ ÁREA GERENCIADA PELO LIVEWIRE (A Ficha do Imóvel) ⚡ --}}
    <div x-data="{ open: @entangle('showFicha') }" x-show="open"
        class="fixed inset-y-0 right-0 z-50 w-96 bg-white dark:bg-gray-800 shadow-2xl border-l border-gray-200 dark:border-gray-700 transform transition-transform duration-300 flex flex-col"
        x-transition:enter="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="translate-x-0"
        x-transition:leave-end="translate-x-full" style="display: none;">

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
                <div class="mb-6 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-xl border border-gray-200 dark:border-gray-600">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Lote / Inscrição</p>
                    <p class="text-xl font-black text-gray-800 dark:text-white">{{ $loteAtivoNome }}</p>
                    <p class="text-sm text-gray-500 mt-2">ID Sistema: #{{ $loteAtivoId }}</p>
                </div>

                {{-- Lista de Ações Futuras --}}
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase pt-5 mb-4">Ações Disponíveis</h3>

                <div class="space-y-3">
                    {{-- Lembrete: Lote vazio também é unidade imobiliária! --}}
                    <button
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Ver
                            Unidades Imobiliárias</span>
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>

                    <button
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Editar
                            Geometria do Lote</span>
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>

                    <button
                        class="w-full text-left px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all group flex items-center justify-between">
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary-600">Adicionar
                            Edificação</span>
                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400 group-hover:text-primary-500" />
                    </button>
                </div>

            @endif
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

                // Mantenha toda a sua configuração do map, layerConfigs e dragElement aqui...
                const view = new ol.View({ center: ol.proj.fromLonLat([centerLon, centerLat]), zoom: initialZoom, maxZoom: 22 });
                const osmLayer = new ol.layer.Tile({ source: new ol.source.OSM() });

                const layerConfigs = {
                    'perimetros': { z: 10, minZoom: 0, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#ef4444', width: 3 }), fill: new ol.style.Fill({ color: 'rgba(239, 68, 68, 0.05)' }) }) },
                    'zonas': { z: 20, minZoom: 0, style: new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#a855f7', width: 2, lineDash: [4, 4] }), fill: new ol.style.Fill({ color: 'rgba(168, 85, 247, 0.1)' }) }) },
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

                const map = new ol.Map({ target: 'sigweb-map', layers: [osmLayer], view: view });
                const loadedLayers = {};

                // Lógica para baixar e desenhar a camada
                const fetchAndDrawLayer = (layerName, checkboxElement) => {
                    if (loadedLayers[layerName]) {
                        loadedLayers[layerName].setVisible(true);
                        return;
                    }

                    const labelSpan = checkboxElement.nextElementSibling;
                    const originalText = labelSpan.innerHTML;
                    labelSpan.innerHTML = 'Carregando...';
                    labelSpan.classList.add('animate-pulse', 'text-primary-500', 'ps-2');

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
                            labelSpan.innerHTML = originalText;
                            labelSpan.classList.remove('animate-pulse', 'text-primary-500', 'ps-2');
                        });
                };

                // Evento de clique em cada caixinha de seleção
                document.querySelectorAll('.layer-toggle').forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        const layerName = this.getAttribute('data-layer');
                        if (this.checked) fetchAndDrawLayer(layerName, this);
                        else if (loadedLayers[layerName]) loadedLayers[layerName].setVisible(false);
                    });

                    // Dispara o carregamento do Perímetro que já começa marcado no HTML
                    if (checkbox.checked) {
                        fetchAndDrawLayer(checkbox.getAttribute('data-layer'), checkbox);
                    }
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
                    }

                    function elementDrag(e) {
                        e.preventDefault();
                        pos1 = pos3 - e.clientX;
                        pos2 = pos4 - e.clientY;
                        pos3 = e.clientX;
                        pos4 = e.clientY;

                        // Cálculo suave
                        requestAnimationFrame(() => {
                            let newTop = elmnt.offsetTop - pos2;
                            let newLeft = elmnt.offsetLeft - pos1;

                            // Limites da tela
                            if (newTop < 10) newTop = 10;
                            if (newLeft < 10) newLeft = 10;
                            if (newTop > window.innerHeight - elmnt.clientHeight - 10)
                                newTop = window.innerHeight - elmnt.clientHeight - 10;
                            if (newLeft > window.innerWidth - elmnt.clientWidth - 10)
                                newLeft = window.innerWidth - elmnt.clientWidth - 10;

                            elmnt.style.top = newTop + "px";
                            elmnt.style.left = newLeft + "px";
                            elmnt.style.right = "auto"; // Importante para o movimento livre
                        });
                    }

                    function closeDragElement() {
                        document.onmouseup = null;
                        document.onmousemove = null;
                    }
                }

                // 1. Lógica da Busca (COM ALERTA BONITO DO FILAMENT)
                const inputBusca = document.getElementById('input-busca-lote');
                if (inputBusca) {
                    inputBusca.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            const numero = this.value;
                            if (!numero) return;

                            fetch(`/api/search-lote?tenant_id=${tenantId}&numero=${numero}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            })
                                .then(async response => {
                                    if (!response.ok) {
                                        const rawText = await response.text();
                                        try {
                                            const errData = JSON.parse(rawText);
                                            throw new Error(errData.message || 'Erro no servidor.');
                                        } catch (err) {
                                            throw new Error('Erro fatal na base de dados.');
                                        }
                                    }
                                    return response.json();
                                })
                                .then(data => {
                                    if (data.coords) {
                                        const targetCoords = ol.proj.fromLonLat([data.coords[0], data.coords[1]]);
                                        view.animate({ center: targetCoords, zoom: 20, duration: 2000 });
                                    }
                                })
                                .catch(err => {
                                    // CHAMA O ALERTA DO FILAMENT PELO LIVEWIRE
                                    @this.mostrarErro(err.message);
                                });
                        }
                    });
                }

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
            });
        </script>
    </div>
</div>