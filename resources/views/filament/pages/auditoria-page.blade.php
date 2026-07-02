<x-filament-panels::page>
    {{ $this->table }}

    {{-- Histórico cartográfico (B13 / item 044): OpenLayers + componente dos mini-mapas Antes/Depois.
         Registrado no LOAD da página para que o modal de detalhes (carregado sob demanda) possa usá-lo. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
    <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('auditoriaGeoMaps', (cfg) => ({
                antigo: cfg.antigo,
                novo: cfg.novo,

                boot() {
                    // x-init pode rodar mais de uma vez (morph do Livewire). O guard por elemento
                    // (dataset.olMounted) garante um único mapa por div, evitando duplicação/overflow.
                    this.aguardarTamanho(0);
                },

                // O modal abre com animação: espera o container ter tamanho real antes de montar.
                aguardarTamanho(tries) {
                    const el = this.$refs.mapaAntigo;
                    if ((!el || el.clientWidth < 10) && tries < 60) {
                        setTimeout(() => this.aguardarTamanho(tries + 1), 50);
                        return;
                    }
                    this.montar(this.$refs.mapaAntigo, this.antigo, '#dc2626');
                    this.montar(this.$refs.mapaNovo, this.novo, '#16a34a');
                },

                montar(el, geom, cor) {
                    if (!el || typeof ol === 'undefined') return;
                    if (el.dataset.olMounted === '1') return; // já montado neste elemento
                    el.dataset.olMounted = '1';
                    el.innerHTML = '';

                    const layers = [new ol.layer.Tile({ source: new ol.source.OSM() })];
                    let src = null;

                    if (geom) {
                        try {
                            const feats = new ol.format.GeoJSON().readFeatures(
                                { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: geom }] },
                                { featureProjection: 'EPSG:3857' }
                            );
                            src = new ol.source.Vector({ features: feats });
                            layers.push(new ol.layer.Vector({
                                source: src,
                                style: new ol.style.Style({
                                    stroke: new ol.style.Stroke({ color: cor, width: 2 }),
                                    fill: new ol.style.Fill({ color: cor + '33' }),
                                }),
                            }));
                        } catch (e) { console.error('GeoJSON inválido:', e); }
                    }

                    const map = new ol.Map({
                        target: el,
                        layers: layers,
                        view: new ol.View({ center: ol.proj.fromLonLat([-51.34, -29.51]), zoom: 15 }),
                    });

                    if (src && src.getFeatures().length) {
                        map.getView().fit(src.getExtent(), { padding: [18, 18, 18, 18], maxZoom: 19 });
                    }

                    // Recalcula o tamanho conforme o modal termina de abrir (tiles só carregam com tamanho > 0).
                    [150, 400, 800].forEach((t) => setTimeout(() => map.updateSize(), t));
                },
            }));
        });
    </script>
</x-filament-panels::page>
