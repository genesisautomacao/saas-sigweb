<x-filament-panels::page>
    @php($situacoes = \App\Filament\Pages\PainelSocialPage::SITUACOES)

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Coluna esquerda: gráfico pizza + legenda clicável --}}
        <div class="lg:col-span-1 flex flex-col gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="font-bold text-sm mb-3 text-gray-700 dark:text-gray-200">Famílias por Situação Cadastral</h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="painel-social-chart"></canvas>
                </div>
                <p class="text-[11px] text-gray-400 mt-2 text-center">
                    Clique numa fatia para destacar as famílias no mapa.
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-sm text-gray-700 dark:text-gray-200">Situações</h3>
                    <button type="button" id="painel-social-limpar"
                        class="text-[11px] text-primary-600 hover:text-primary-700 font-semibold hidden">
                        Ver todas
                    </button>
                </div>
                <div class="flex flex-col gap-1" id="painel-social-legenda">
                    @foreach($situacoes as $key => $info)
                        <button type="button" data-situacao="{{ $key }}"
                            class="painel-social-item flex items-center justify-between gap-2 px-2 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-left transition-colors">
                            <span class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $info['cor'] }}"></span>
                                {{ $info['label'] }}
                            </span>
                            <span class="text-xs font-bold text-gray-500" data-count="{{ $key }}">
                                {{ $this->distribuicao[$key] ?? 0 }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Coluna direita: mapa --}}
        <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden relative">
            <div id="painel-social-mapa" style="width: 100%; height: 70vh; min-height: 460px;"></div>
            <div id="painel-social-vazio"
                class="absolute inset-0 flex-col items-center justify-center text-gray-400 bg-white/70 dark:bg-gray-800/70 hidden">
                <x-heroicon-o-map-pin class="w-10 h-10 mb-2 opacity-30" />
                <p class="text-sm font-medium">Nenhuma família com localização</p>
                <p class="text-xs mt-1">Vincule a moradia (unidade imobiliária) ou o empreendimento à família.</p>
            </div>
        </div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const SITUACOES = @json($situacoes);
            const familias = @json($this->familias);
            const distribuicao = @json($this->distribuicao);

            const keys = Object.keys(SITUACOES);
            let situacaoAtiva = null; // null = todas

            // ---------- MAPA ----------
            const mapDiv = document.getElementById('painel-social-mapa');
            const vazio = document.getElementById('painel-social-vazio');
            let map, layerGroup;

            if (mapDiv && typeof L !== 'undefined') {
                map = L.map('painel-social-mapa').setView([-15.8, -47.9], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
                layerGroup = L.layerGroup().addTo(map);
                setTimeout(() => map.invalidateSize(), 200);
            }

            function markerIcon(cor) {
                return L.divIcon({
                    className: '',
                    html: `<div style="width:16px;height:16px;border-radius:50%;background:${cor};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
                    iconSize: [16, 16], iconAnchor: [8, 8],
                });
            }

            function plot() {
                if (!map) return;
                layerGroup.clearLayers();
                const filtradas = situacaoAtiva ? familias.filter(f => f.situacao === situacaoAtiva) : familias;

                filtradas.forEach(f => {
                    const cor = (SITUACOES[f.situacao] || {}).cor || '#9ca3af';
                    const label = (SITUACOES[f.situacao] || {}).label || f.situacao;
                    L.marker([f.lat, f.lon], { icon: markerIcon(cor) })
                        .addTo(layerGroup)
                        .bindPopup(`<strong>${f.rf}</strong><br>Situação: ${label}`);
                });

                vazio.classList.toggle('hidden', familias.length > 0);
                vazio.classList.toggle('flex', familias.length === 0);

                if (filtradas.length > 0) {
                    map.fitBounds(L.latLngBounds(filtradas.map(f => [f.lat, f.lon])), { padding: [40, 40], maxZoom: 17 });
                }
            }

            // ---------- GRÁFICO PIZZA ----------
            const ctx = document.getElementById('painel-social-chart');
            let chart;
            if (ctx && typeof Chart !== 'undefined') {
                chart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: keys.map(k => SITUACOES[k].label),
                        datasets: [{
                            data: keys.map(k => distribuicao[k] || 0),
                            backgroundColor: keys.map(k => SITUACOES[k].cor),
                            borderColor: '#fff',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        onClick: (evt, elements) => {
                            if (elements.length > 0) {
                                aplicarFiltro(keys[elements[0].index]);
                            }
                        },
                    },
                });
            }

            // ---------- FILTRO (interação gráfico ↔ mapa) ----------
            const btnLimpar = document.getElementById('painel-social-limpar');

            function aplicarFiltro(situacao) {
                situacaoAtiva = (situacaoAtiva === situacao) ? null : situacao;
                plot();
                document.querySelectorAll('.painel-social-item').forEach(el => {
                    el.classList.toggle('ring-2', el.dataset.situacao === situacaoAtiva);
                    el.classList.toggle('ring-primary-400', el.dataset.situacao === situacaoAtiva);
                    el.classList.toggle('bg-gray-50', el.dataset.situacao === situacaoAtiva);
                });
                btnLimpar.classList.toggle('hidden', situacaoAtiva === null);
            }

            document.querySelectorAll('.painel-social-item').forEach(el => {
                el.addEventListener('click', () => aplicarFiltro(el.dataset.situacao));
            });
            if (btnLimpar) btnLimpar.addEventListener('click', () => aplicarFiltro(situacaoAtiva));

            plot();
        });
    </script>
    @endpush
</x-filament-panels::page>
