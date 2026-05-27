<x-filament-panels::page>
    <div class="flex flex-col gap-4">

        {{-- Cabeçalho de status --}}
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Atualiza automaticamente a cada 30s
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full
                {{ count($this->cadastradores) > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-500' }}">
                <span class="w-2 h-2 rounded-full {{ count($this->cadastradores) > 0 ? 'bg-green-500 animate-pulse' : 'bg-gray-400' }}"></span>
                {{ count($this->cadastradores) }} ativo(s)
            </span>
        </div>

        {{-- Lista horizontal de cadastradores --}}
        @if(count($this->cadastradores) > 0)
            <div class="flex gap-3 overflow-x-auto pb-2">
                @foreach($this->cadastradores as $c)
                    <div class="flex-shrink-0 w-72 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm
                                cursor-pointer hover:border-primary-400 transition-colors"
                         onclick="flyToCadastrador({{ $c['lat'] }}, {{ $c['lon'] }})">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center flex-shrink-0">
                                <span class="text-primary-700 dark:text-primary-300 font-bold text-sm">
                                    {{ strtoupper(substr($c['name'], 0, 1)) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm truncate">{{ $c['name'] }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $c['email'] }}</p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 rounded-full font-medium">
                                        {{ $c['coletados_hoje'] }} coletados hoje
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    Últ. sinal: {{ \Carbon\Carbon::parse($c['updated_at'])->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-8 text-center text-gray-400 bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                <x-heroicon-o-map-pin class="w-10 h-10 mb-2 opacity-30" />
                <p class="text-sm font-medium">Nenhum cadastrador ativo</p>
                <p class="text-xs mt-1">Aparecem aqui os que enviaram GPS nos últimos 10 min.</p>
            </div>
        @endif

        {{-- Mapa Leaflet em largura total --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div id="mapa-monitoramento" style="width: 100%; height: 60vh; min-height: 400px;"></div>
        </div>

    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapDiv = document.getElementById('mapa-monitoramento');
            if (!mapDiv || typeof L === 'undefined') return;

            const cadastradores = @json($this->cadastradores);

            const map = L.map('mapa-monitoramento').setView([-15.8, -47.9], 5);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);

            // Garante o redimensionamento correto após o layout aplicar
            setTimeout(() => map.invalidateSize(), 200);

            const markers = {};

            function buildIcon(initial) {
                return L.divIcon({
                    className: '',
                    html: `<div style="
                        width:36px;height:36px;border-radius:50%;
                        background:#6366f1;color:#fff;
                        display:flex;align-items:center;justify-content:center;
                        font-weight:bold;font-size:14px;
                        border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);">
                        ${initial}
                    </div>`,
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                });
            }

            cadastradores.forEach(function (c) {
                const m = L.marker([c.lat, c.lon], { icon: buildIcon(c.name.charAt(0).toUpperCase()) })
                    .addTo(map)
                    .bindPopup(`<strong>${c.name}</strong><br>${c.coletados_hoje} coletados hoje`);
                markers[c.user_id] = m;
            });

            if (cadastradores.length > 0) {
                const bounds = L.latLngBounds(cadastradores.map(c => [c.lat, c.lon]));
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
            }

            window.flyToCadastrador = function (lat, lon) {
                map.flyTo([lat, lon], 17, { duration: 1.2 });
                Object.values(markers).forEach(m => {
                    if (m.getLatLng().lat === lat && m.getLatLng().lng === lon) {
                        m.openPopup();
                    }
                });
            };
        });
    </script>
    @endpush
</x-filament-panels::page>
