@php
    $tenant = auth()->user()->tenants->first() ?? \App\Models\Tenant::first();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="mapaSelecaoLote({
            state: $wire.$entangle('{{ $getStatePath() }}'),
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