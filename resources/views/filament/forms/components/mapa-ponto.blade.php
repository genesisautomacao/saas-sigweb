@php
    $tenant = auth()->user()?->tenants->first() ?? \App\Models\Tenant::first();
    $isDisabled = $disabled ?? false;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="mapaPonto({
            state: $wire.$entangle('{{ $getStatePath() }}'),
            disabled: {{ $isDisabled ? 'true' : 'false' }},
            centerLat: {{ $tenant->data['map_lat'] ?? -29.51 }},
            centerLon: {{ $tenant->data['map_lon'] ?? -51.34 }},
            centerZoom: {{ $tenant->data['map_zoom'] ?? 15 }}
        })"
    >
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>

        <div class="rounded-xl border-2 border-gray-300 dark:border-gray-700 bg-gray-100 overflow-hidden shadow-sm relative">
            <div x-ref="mapContainer" style="width: 100%; height: 350px;" :style="disabled ? 'cursor: default' : 'cursor: crosshair'"></div>

            <div x-show="temPonto()" x-transition class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-emerald-600 text-white px-4 py-2 rounded-full font-semibold shadow-lg text-xs flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span x-text="rotulo()"></span>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2" x-show="!disabled"><strong>Dica:</strong> clique no mapa para marcar a posição.</p>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapaPonto', (config) => ({
                state: config.state,
                disabled: config.disabled,
                map: null,
                markerLayer: null,
                markerSource: null,

                temPonto() {
                    return this.state && this.state.lat != null && this.state.lon != null;
                },
                rotulo() {
                    if (!this.temPonto()) return '';
                    return Number(this.state.lat).toFixed(5) + ', ' + Number(this.state.lon).toFixed(5);
                },

                desenharMarcador() {
                    this.markerSource.clear();
                    if (this.temPonto()) {
                        const feat = new ol.Feature({
                            geometry: new ol.geom.Point(ol.proj.fromLonLat([Number(this.state.lon), Number(this.state.lat)]))
                        });
                        this.markerSource.addFeature(feat);
                    }
                },

                init() {
                    this.markerSource = new ol.source.Vector();
                    this.markerLayer = new ol.layer.Vector({
                        source: this.markerSource,
                        style: new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 8,
                                fill: new ol.style.Fill({ color: '#ef4444' }),
                                stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                            })
                        })
                    });

                    const baseLayer = new ol.layer.Tile({ source: new ol.source.OSM() });

                    // Centraliza no ponto salvo, se houver; senão no centro da cidade.
                    const center = this.temPonto()
                        ? ol.proj.fromLonLat([Number(this.state.lon), Number(this.state.lat)])
                        : ol.proj.fromLonLat([config.centerLon, config.centerLat]);

                    this.map = new ol.Map({
                        target: this.$refs.mapContainer,
                        layers: [baseLayer, this.markerLayer],
                        view: new ol.View({
                            center: center,
                            zoom: this.temPonto() ? 18 : config.centerZoom,
                            maxZoom: 22
                        })
                    });

                    this.desenharMarcador();

                    const resizeObserver = new ResizeObserver(() => { if (this.map) this.map.updateSize(); });
                    resizeObserver.observe(this.$refs.mapContainer);

                    if (!this.disabled) {
                        this.map.on('singleclick', (evt) => {
                            const lonLat = ol.proj.toLonLat(evt.coordinate);
                            this.state = { lon: lonLat[0], lat: lonLat[1] };
                            this.desenharMarcador();
                        });
                    }
                }
            }));
        });
    </script>
</x-dynamic-component>
