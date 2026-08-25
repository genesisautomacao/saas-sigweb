{{-- Viewer 360 (Pannellum) com navegação estilo STREET VIEW (Bom Princípio/Líder):
     as setas dentro do panorama apontam para os pontos vizinhos (anterior/próximo
     da trajetória + cruzamentos) — o clique troca a foto via $wire.panoramaDados()
     sem fechar o modal. O yaw da seta = bearing real − azimuth da captura. --}}
<div class="flex flex-col gap-2"
    x-data="{
        atual: @js($dados),
        aviso: null,
        carregando: false,
        initPannellum() {
            if (typeof pannellum === 'undefined') {
                let link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css';
                document.head.appendChild(link);

                let script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js';
                script.onload = () => this.renderizarPanorama();
                document.head.appendChild(script);
            } else {
                this.renderizarPanorama();
            }
        },
        yawDe(bearing) {
            let y = bearing - (this.atual.azimuth || 0);
            while (y > 180) y -= 360;
            while (y < -180) y += 360;
            return y;
        },
        setasHotspots() {
            return (this.atual.setas || []).map(s => ({
                pitch: -12,
                yaw: this.yawDe(s.bearing),
                cssClass: 'pano-seta',
                clickHandlerFunc: () => this.navegar(s.id)
            }));
        },
        renderizarPanorama() {
            setTimeout(() => {
                if (window._panoViewer) {
                    try { window._panoViewer.destroy(); } catch (e) {}
                    window._panoViewer = null;
                }
                window._panoViewer = pannellum.viewer('{{ $uniqueId }}', {
                    'type': 'equirectangular',
                    'panorama': this.atual.url,
                    'autoLoad': true,
                    'compass': true,
                    'northOffset': this.atual.azimuth || 0,
                    'showZoomCtrl': true,
                    'mouseZoom': true,
                    'hotSpots': this.setasHotspots()
                });
            }, 300);
        },
        async navegar(id) {
            if (this.carregando) return;
            this.aviso = null;
            this.carregando = true;
            try {
                const d = await this.$wire.call('panoramaDados', id);
                if (!d || !d.url) {
                    this.aviso = '📤 A foto deste ponto ainda não foi enviada ao bucket.';
                    return;
                }
                this.atual = d;
                this.renderizarPanorama();
            } finally {
                this.carregando = false;
            }
        }
    }"
    x-init="initPannellum()">

    <style>
        .pano-seta {
            width: 44px;
            height: 44px;
            margin-left: -22px;
            margin-top: -22px;
            border-radius: 9999px;
            background: rgba(17, 24, 39, .65);
            border: 2px solid #fff;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .45);
            transition: transform .15s ease, background .15s ease;
        }

        .pano-seta::after {
            content: '⬆';
            color: #fff;
            font-size: 22px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .pano-seta:hover {
            transform: scale(1.2);
            background: rgba(59, 130, 246, .9);
        }
    </style>

    <div class="flex justify-between items-center mb-2">
        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
            <x-heroicon-o-camera class="w-6 h-6 text-blue-500" />
            <span x-text="atual.titulo">{{ $ponto->titulo }}</span>
        </h3>
        @if($badge)
            <span class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded border border-amber-200">
                {{ $badge }}
            </span>
        @endif
    </div>

    <div x-show="aviso" x-cloak
        class="px-3 py-2 bg-amber-100 text-amber-800 text-xs font-bold rounded border border-amber-200"
        x-text="aviso"></div>

    {{-- Container protegido do Livewire para o JS poder brincar com o Canvas livremente --}}
    <div wire:ignore>
        <div id="{{ $uniqueId }}"
            class="w-full rounded-xl overflow-hidden shadow-inner border border-gray-300 dark:border-gray-700 bg-gray-200"
            style="height: 500px;"></div>
    </div>

    <p class="text-[11px] text-gray-400" x-show="(atual.setas || []).length > 0">
        🚗 Clique nas setas dentro da imagem para navegar ao próximo ponto da rua.
    </p>
</div>
