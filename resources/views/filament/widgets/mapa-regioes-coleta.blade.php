@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $cadastradores = $this->getCadastradores();
    $mapaId = 'mapa-regioes-'.uniqid();
@endphp

{{-- OpenLayers via @assets: o Livewire garante o carregamento UMA vez e ANTES do @script,
     inclusive quando o componente é renderizado dinamicamente. --}}
@assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
    <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
@endassets

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-map"
        :collapsible="true"
        heading="Mapa das regiões (atribuições vigentes hoje)"
        description="Cada cor é um cadastrador. Quadras sem cor estão livres para atribuição."
    >
        @if (empty($cadastradores))
            <p class="text-sm text-gray-500 italic">Nenhuma região atribuída para hoje.</p>
        @else
            {{-- Legenda: cadastradores em atuação (espaçamento inline: não depende do
                 Tailwind arbitrário do painel, que nem sempre compila estas classes) --}}
            <div class="flex flex-wrap text-xs" style="gap:14px; margin-bottom:18px;">
                @foreach ($cadastradores as $c)
                    <span class="inline-flex items-center gap-1.5">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ $c['cor'] }};"></span>
                        <strong>{{ $c['nome'] }}</strong>
                        <span class="text-gray-500">({{ $c['quadras'] }} quadra{{ $c['quadras'] === 1 ? '' : 's' }})</span>
                    </span>
                @endforeach
            </div>

            <div wire:ignore class="rounded-xl border border-gray-300 dark:border-gray-700 overflow-hidden relative" style="margin-top:4px;">
                <div id="{{ $mapaId }}" style="width:100%; height:420px;"></div>
                <div id="{{ $mapaId }}-info" style="display:none; position:absolute; bottom:12px; left:12px; z-index:1000; background:#111827; color:#fff; padding:6px 12px; border-radius:8px; font-size:12px;"></div>
            </div>

            @script
            <script>
                (() => {
                    const cfg = {
                        alvo: @js($mapaId),
                        tenantId: @js($tenant?->id ?? 0),
                        centerLat: @js((float) data_get($tenant?->data, 'map_lat', -26.96)),
                        centerLon: @js((float) data_get($tenant?->data, 'map_lon', -50.41)),
                        apiUrl: @js(route('coleta.quadras-geojson')),
                        cores: @js(collect($cadastradores)->pluck('cor', 'user_id')),
                    };

                    const rgba = (hex, a) => {
                        const n = parseInt(String(hex).replace('#', ''), 16);
                        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
                    };

                    const iniciar = () => {
                        const el = document.getElementById(cfg.alvo);
                        const info = document.getElementById(cfg.alvo + '-info');
                        if (!el || el.dataset.pronto) return;
                        el.dataset.pronto = '1';

                        const fonte = new ol.source.Vector();
                        let mapa;

                        const camada = new ol.layer.Vector({
                            source: fonte,
                            style: (f) => {
                                const donoId = f.get('ocupada_por_id');
                                const cor = donoId ? (cfg.cores[donoId] || '#6b7280') : null;
                                const zoom = mapa ? mapa.getView().getZoom() : 0;

                                const s = new ol.style.Style({
                                    fill: new ol.style.Fill({ color: cor ? rgba(cor, .45) : 'rgba(148,163,184,.10)' }),
                                    stroke: new ol.style.Stroke({ color: cor || '#94a3b8', width: cor ? 2 : 1 }),
                                });

                                if (zoom > 14.5) {
                                    s.setText(new ol.style.Text({
                                        text: String(f.get('name') || ''),
                                        font: 'bold 11px Arial, sans-serif',
                                        fill: new ol.style.Fill({ color: '#1e293b' }),
                                        stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 }),
                                        overflow: true,
                                    }));
                                }
                                return s;
                            },
                        });

                        mapa = new ol.Map({
                            target: el,
                            layers: [new ol.layer.Tile({ source: new ol.source.OSM() }), camada],
                            view: new ol.View({
                                center: ol.proj.fromLonLat([cfg.centerLon, cfg.centerLat]),
                                zoom: 14, maxZoom: 22,
                            }),
                        });

                        mapa.on('moveend', () => camada.changed());
                        new ResizeObserver(() => mapa.updateSize()).observe(el);

                        fetch(`${cfg.apiUrl}?tenant_id=${cfg.tenantId}`)
                            .then(r => r.json())
                            .then(geo => {
                                fonte.addFeatures(new ol.format.GeoJSON({
                                    dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857',
                                }).readFeatures(geo));
                                if (fonte.getFeatures().length) {
                                    mapa.getView().fit(fonte.getExtent(), { padding: [30, 30, 30, 30], maxZoom: 17 });
                                }
                            })
                            .catch(e => console.error('[mapa-regioes] falha ao carregar quadras', e));

                        // Tooltip: quadra + cadastrador + lotes
                        mapa.on('pointermove', (evt) => {
                            const f = mapa.forEachFeatureAtPixel(evt.pixel, (feat) => feat);
                            if (!f) { info.style.display = 'none'; return; }
                            info.textContent = `Quadra ${f.get('name') || ''}`
                                + (f.get('ocupada_por') ? ` · ${f.get('ocupada_por')} (${f.get('periodo') || ''})` : ' · livre')
                                + ` · ${f.get('total_lotes') || 0} lotes`;
                            info.style.display = 'block';
                        });
                    };

                    // A seção é colapsável: se abrir depois, o mapa precisa recalcular o tamanho.
                    const pronto = () => (typeof ol !== 'undefined') ? iniciar() : setTimeout(pronto, 100);
                    pronto();
                })();
            </script>
            @endscript
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
