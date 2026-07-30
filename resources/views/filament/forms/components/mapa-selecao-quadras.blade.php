@php
    $tenant = \Filament\Facades\Filament::getTenant();
@endphp

{{-- R67-4 — seleção da região do cadastrador clicando nas quadras do mapa.
     Quadras já atribuídas a OUTRO cadastrador no mesmo período aparecem em vermelho
     e não podem ser selecionadas (o servidor revalida no salvamento). --}}

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="mapaSelecaoQuadras({
            state: $wire.$entangle('{{ $getStatePath() }}'),
            tenantId: {{ $tenant?->id ?? 0 }},
            centerLat: {{ data_get($tenant?->data, 'map_lat', -26.96) }},
            centerLon: {{ data_get($tenant?->data, 'map_lon', -50.41) }},
            apiUrl: '{{ route('coleta.quadras-geojson') }}',
            registroId: {{ $getRecord()?->id ?? 0 }},
        })"
    >
        {{-- OpenLayers carregado DENTRO do wrapper do campo. NÃO usar a diretiva de assets
             do Livewire aqui: ela emite markup fora do wrapper e quebra a regra de
             "um único elemento raiz por componente". Mesmo padrão do mapa-selecao-lote. --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
        <script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>

        <div class="rounded-xl border-2 border-gray-300 dark:border-gray-700 bg-gray-100 overflow-hidden shadow-sm relative">
            <div x-ref="mapContainer" style="width: 100%; height: 460px; cursor: pointer;"></div>

            {{-- Busca por nome da quadra --}}
            <div style="position:absolute; top:12px; left:56px; z-index:1000; width:min(320px, calc(100% - 72px));">
                <input type="text" x-model="busca" @input="filtrar()"
                    placeholder="Buscar quadra pelo nome..."
                    style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:8px; background:#fff; padding:9px 12px; font-size:13px; color:#111827; box-shadow:0 2px 6px rgba(0,0,0,.18); outline:none;" />
            </div>

            {{-- Legenda --}}
            <div style="position:absolute; bottom:12px; left:12px; z-index:1000; background:#fff; border-radius:8px; padding:8px 10px; box-shadow:0 2px 8px rgba(0,0,0,.2); font-size:11px; line-height:1.7;">
                <div><span style="display:inline-block;width:12px;height:12px;background:rgba(59,130,246,.25);border:1px solid #3b82f6;vertical-align:-2px;"></span> Disponível</div>
                <div><span style="display:inline-block;width:12px;height:12px;background:rgba(16,185,129,.55);border:2px solid #059669;vertical-align:-2px;"></span> Selecionada</div>
                <div><span style="display:inline-block;width:12px;height:12px;background:rgba(239,68,68,.35);border:1px solid #dc2626;vertical-align:-2px;"></span> De outro cadastrador</div>
            </div>

            {{-- Contador --}}
            <div style="position:absolute; top:12px; right:12px; z-index:1000; background:#059669; color:#fff; border-radius:9999px; padding:6px 14px; font-size:12px; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,.2);">
                <span x-text="(state?.length || 0) + ' quadra(s)'"></span>
                <span x-show="totalLotes > 0" x-text="' · ' + totalLotes + ' lotes'"></span>
            </div>

            {{-- Aviso ao clicar em quadra ocupada --}}
            <div x-show="aviso" x-transition style="position:absolute; bottom:12px; left:50%; transform:translateX(-50%); z-index:1001; background:#b91c1c; color:#fff; padding:8px 16px; border-radius:9999px; font-size:12px; font-weight:600; box-shadow:0 2px 10px rgba(0,0,0,.3);">
                <span x-text="aviso"></span>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-2">
            <strong>Dica:</strong> clique nas quadras para incluir/remover da região. Quadras em vermelho já pertencem a outro cadastrador no mesmo período.
        </p>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapaSelecaoQuadras', (config) => ({
                state: config.state || [],
                map: null,
                camada: null,
                fonte: null,
                busca: '',
                aviso: '',
                totalLotes: 0,

                cor(f) {
                    const id = f.get('id');
                    const selecionada = (this.state || []).map(Number).includes(Number(id));
                    if (selecionada) return { fill: 'rgba(16,185,129,.55)', stroke: '#059669', w: 2.5 };
                    if (f.get('ocupada_por')) return { fill: 'rgba(239,68,68,.35)', stroke: '#dc2626', w: 1.5 };
                    return { fill: 'rgba(59,130,246,.15)', stroke: '#3b82f6', w: 1.5 };
                },

                estilo(f) {
                    const c = this.cor(f);
                    const zoom = this.map ? this.map.getView().getZoom() : 0;
                    const oculta = this.busca && !String(f.get('name') || '').toLowerCase().includes(this.busca.toLowerCase());

                    const s = new ol.style.Style({
                        fill: new ol.style.Fill({ color: oculta ? 'rgba(0,0,0,.03)' : c.fill }),
                        stroke: new ol.style.Stroke({ color: oculta ? '#d1d5db' : c.stroke, width: c.w }),
                    });

                    if (zoom > 14.5 && !oculta) {
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

                atualizarTotais() {
                    let n = 0;
                    (this.fonte?.getFeatures() || []).forEach(f => {
                        if ((this.state || []).map(Number).includes(Number(f.get('id')))) n += (f.get('total_lotes') || 0);
                    });
                    this.totalLotes = n;
                },

                filtrar() { this.camada?.changed(); },

                url() {
                    const p = new URLSearchParams({ tenant_id: config.tenantId, ignorar_id: config.registroId || 0 });
                    const ini = this.$wire.get('data.data_inicio');
                    const fim = this.$wire.get('data.data_fim');
                    if (ini) p.set('data_inicio', ini);
                    if (fim) p.set('data_fim', fim);
                    return `${config.apiUrl}?${p.toString()}`;
                },

                recarregar() {
                    if (!this.fonte) return;
                    fetch(this.url())
                        .then(r => r.json())
                        .then(geo => {
                            this.fonte.clear();
                            this.fonte.addFeatures(new ol.format.GeoJSON({
                                dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857',
                            }).readFeatures(geo));
                            this.camada.changed();
                            this.atualizarTotais();
                        });
                },

                init() {
                    // Espera o OpenLayers terminar de carregar antes de montar o mapa.
                    if (typeof ol === 'undefined') {
                        setTimeout(() => this.init(), 100);
                        return;
                    }

                    this.fonte = new ol.source.Vector();
                    this.camada = new ol.layer.Vector({ source: this.fonte, style: (f) => this.estilo(f) });

                    this.map = new ol.Map({
                        target: this.$refs.mapContainer,
                        layers: [new ol.layer.Tile({ source: new ol.source.OSM() }), this.camada],
                        view: new ol.View({
                            center: ol.proj.fromLonLat([config.centerLon, config.centerLat]),
                            zoom: 14, maxZoom: 22,
                        }),
                    });

                    this.map.on('moveend', () => this.camada.changed());
                    new ResizeObserver(() => this.map?.updateSize()).observe(this.$refs.mapContainer);

                    // Carrega as quadras e enquadra o município
                    fetch(this.url())
                        .then(r => r.json())
                        .then(geo => {
                            this.fonte.addFeatures(new ol.format.GeoJSON({
                                dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857',
                            }).readFeatures(geo));
                            if (this.fonte.getFeatures().length) {
                                this.map.getView().fit(this.fonte.getExtent(), { padding: [30, 30, 30, 30], maxZoom: 17 });
                            }
                            this.atualizarTotais();
                        });

                    // Mudou o período? recarrega a disponibilidade das quadras
                    // ($wire.$watch é a API do Livewire 3; protegido para não quebrar o mapa)
                    try {
                        this.$wire.$watch('data.data_inicio', () => this.recarregar());
                        this.$wire.$watch('data.data_fim', () => this.recarregar());
                    } catch (e) {
                        console.warn('[mapa-quadras] não foi possível observar as datas', e);
                    }

                    // Clique: inclui/remove a quadra da região (bloqueia as de outro cadastrador)
                    this.map.on('singleclick', (evt) => {
                        this.map.forEachFeatureAtPixel(evt.pixel, (f, layer) => {
                            if (layer !== this.camada) return;

                            const dono = f.get('ocupada_por');
                            if (dono) {
                                this.aviso = `Quadra ${f.get('name')} já está com ${dono} (${f.get('periodo') || ''})`;
                                setTimeout(() => { this.aviso = ''; }, 3500);
                                return true;
                            }

                            const id = Number(f.get('id'));
                            const atual = (this.state || []).map(Number);
                            this.state = atual.includes(id) ? atual.filter(x => x !== id) : [...atual, id];

                            this.camada.changed();
                            this.atualizarTotais();
                            return true;
                        });
                    });
                },
            }));
        });
    </script>
</x-dynamic-component>
