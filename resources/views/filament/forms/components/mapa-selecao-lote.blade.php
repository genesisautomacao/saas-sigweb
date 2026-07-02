@php
    $tenant = auth()->user()->tenants->first() ?? \App\Models\Tenant::first();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="mapaSelecaoLote({
            state: $wire.$entangle('{{ $getStatePath() }}').live,
            tenantId: {{ $tenant->id ?? 1 }},
            centerLat: {{ $tenant->data['map_lat'] ?? -29.51 }}, 
            centerLon: {{ $tenant->data['map_lon'] ?? -51.34 }},
            apiUrl: '{{ url('/cidadao/lotes-geojson') }}' {{-- 🛑 A NOVA ROTA AQUI! --}}
        })"
    >
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>

        <div class="rounded-xl border-2 border-gray-300 dark:border-gray-700 bg-gray-100 overflow-hidden shadow-sm relative">
            <div x-ref="mapContainer" style="width: 100%; height: 400px; cursor: crosshair;"></div>

            {{-- Barra de busca (nº do lote / código tributário / endereço) — voa e seleciona o lote.
                 Estilos inline de propósito: não dependem do CSS do painel (Tailwind arbitrário
                 não é compilado aqui) e ficam SEMPRE acima do mapa (z-index alto). --}}
            <div style="position:absolute; top:12px; left:56px; z-index:1000; width:min(400px, calc(100% - 72px));">
                <div style="position:relative;">
                    <input type="text" x-model="busca" @input.debounce.400ms="buscar()"
                        placeholder="Buscar por nº do lote, código tributário ou endereço..."
                        style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:8px; background:#ffffff; padding:9px 12px; font-size:13px; color:#111827; box-shadow:0 2px 6px rgba(0,0,0,.18); outline:none;" />
                    <div x-show="resultados.length > 0" x-transition @click.outside="resultados = []"
                        style="position:absolute; margin-top:4px; width:100%; max-height:220px; overflow:auto; border:1px solid #e5e7eb; border-radius:8px; background:#ffffff; box-shadow:0 6px 16px rgba(0,0,0,.2); z-index:1001;">
                        <template x-for="(r, i) in resultados" :key="i">
                            <button type="button" @click="selecionarBusca(r)"
                                style="display:block; width:100%; text-align:left; padding:8px 12px; border:0; border-bottom:1px solid #f3f4f6; background:transparent; cursor:pointer;"
                                onmouseover="this.style.background='#ecfdf5'" onmouseout="this.style.background='transparent'">
                                <span style="display:block; font-size:13px; font-weight:600; color:#1f2937;" x-text="r.titulo"></span>
                                <span style="display:block; font-size:11px; color:#6b7280;" x-text="r.subtitulo"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="state" x-transition class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-emerald-600 text-white px-4 py-2 rounded-full font-bold shadow-lg text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Imóvel Selecionado!
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2"><strong>Dica:</strong> Aproxime o mapa e clique sobre o polígono do seu terreno.</p>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapaSelecaoLote', (config) => ({
                state: config.state,
                map: null,
                vectorLayer: null,
                busca: '',
                resultados: [],

                // Autocomplete usando o MESMO endpoint do mapa público (nº do lote, código
                // tributário, inscrição, endereço, edifício). Modo publico=1 (sem dados sensíveis).
                buscar() {
                    const q = (this.busca || '').trim();
                    if (q.length < 2) { this.resultados = []; return; }
                    fetch(`/api/search-lote?tenant_id=${config.tenantId}&termo=${encodeURIComponent(q)}&publico=1`)
                        .then(r => r.json())
                        .then(data => { this.resultados = Array.isArray(data) ? data : []; })
                        .catch(() => { this.resultados = []; });
                },

                // Ao escolher: voa/zoom até o resultado (padrão do mapa público) e, se for um
                // lote/edifício, já SELECIONA o lote para o processo.
                selecionarBusca(res) {
                    this.resultados = [];
                    this.busca = '';
                    if (!res || !Array.isArray(res.coords)) return;

                    const target = ol.proj.fromLonLat([res.coords[0], res.coords[1]]);
                    this.map.getView().animate({ center: target, zoom: 20, duration: 1500 });

                    if (res.id && (res.tipo === 'lote' || res.tipo === 'edificio')) {
                        this.state = res.id;
                        if (this.vectorLayer) this.vectorLayer.changed();
                    }
                },

                init() {
                    const vectorSource = new ol.source.Vector({
                        url: `${config.apiUrl}?tenant_id=${config.tenantId}`,
                        format: new ol.format.GeoJSON({
                            dataProjection: 'EPSG:4326',
                            featureProjection: 'EPSG:3857'
                        })
                    });

                    // 🛑 A NOVA REGRA DE ESTILO (Com Texto Dinâmico)
                    this.vectorLayer = new ol.layer.Vector({
                        source: vectorSource,
                        style: (feature) => {
                            const isSelected = feature.get('id') == this.state;
                            const numLote = feature.get('numero_lote') || 'S/N';
                            // Pega o zoom atual (se o mapa já existir)
                            const zoom = this.map ? this.map.getView().getZoom() : 0;

                            const style = new ol.style.Style({
                                stroke: new ol.style.Stroke({ 
                                    color: isSelected ? '#10b981' : '#3b82f6', 
                                    width: isSelected ? 3 : 2 
                                }),
                                fill: new ol.style.Fill({ 
                                    color: isSelected ? 'rgba(16, 185, 129, 0.6)' : 'rgba(59, 130, 246, 0.15)' 
                                })
                            });

                            // Mostra o número apenas se o Zoom estiver bem próximo (> 18.5)
                            if (zoom > 18.5) {
                                style.setText(new ol.style.Text({
                                    text: String(numLote),
                                    font: 'bold 12px Arial, sans-serif',
                                    fill: new ol.style.Fill({ color: '#1e293b' }), // Texto Escuro
                                    stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }), // Borda Branca
                                    overflow: true
                                }));
                            }

                            return style;
                        }
                    });

                    const baseLayer = new ol.layer.Tile({ source: new ol.source.OSM() });

                    this.map = new ol.Map({
                        target: this.$refs.mapContainer,
                        layers: [baseLayer, this.vectorLayer],
                        view: new ol.View({
                            center: ol.proj.fromLonLat([config.centerLon, config.centerLat]),
                            zoom: 15,
                            maxZoom: 22
                        })
                    });

                    // 🛑 Força o mapa a redesenhar os polígonos (e o texto) quando o zoom terminar
                    this.map.on('moveend', () => {
                        this.vectorLayer.changed();
                    });

                    const resizeObserver = new ResizeObserver(() => { if (this.map) this.map.updateSize(); });
                    resizeObserver.observe(this.$refs.mapContainer);

                    vectorSource.once('change', () => {
                        if (vectorSource.getState() === 'ready' && vectorSource.getFeatures().length > 0) {
                            
                            // 🛑 A INTELIGÊNCIA DO ZOOM (Sniper Mode)
                            if (this.state) {
                                // Se já tem lote selecionado (rascunho), procura-o no mapa
                                let selectedFeature = null;
                                vectorSource.getFeatures().forEach(f => {
                                    if (f.get('id') == this.state) {
                                        selectedFeature = f;
                                    }
                                });

                                if (selectedFeature) {
                                    // Dá um zoom cravado direto na casa
                                    this.map.getView().fit(selectedFeature.getGeometry().getExtent(), { 
                                        padding: [100, 100, 100, 100], 
                                        maxZoom: 20, 
                                        duration: 1500 
                                    });
                                } else {
                                    // Se não achar o lote (ex: foi apagado), foca na cidade
                                    this.map.getView().fit(vectorSource.getExtent(), { padding: [40, 40, 40, 40], maxZoom: 18, duration: 1000 });
                                }
                            } else {
                                // Processo Novo: Foca na cidade toda
                                this.map.getView().fit(vectorSource.getExtent(), { padding: [40, 40, 40, 40], maxZoom: 18, duration: 1000 });
                            }
                        }
                    });

                    this.map.on('singleclick', (evt) => {
                        this.map.forEachFeatureAtPixel(evt.pixel, (feature, layer) => {
                            if (layer === this.vectorLayer) {
                                this.state = feature.get('id');
                                this.vectorLayer.changed();
                                return true; 
                            }
                        });
                    });
                }
            }));
        });
    </script>
</x-dynamic-component>