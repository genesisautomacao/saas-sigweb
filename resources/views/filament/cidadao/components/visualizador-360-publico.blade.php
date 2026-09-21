{{-- Visualizador 360 do MAPA PÚBLICO (só leitura) com navegação estilo STREET VIEW (2026-09-21).
     Espelho do viewer interno (filament/pages/panorama-viewer.blade.php): as setas dentro do
     panorama apontam para os pontos vizinhos (anterior/próximo da trajetória + cruzamentos) e o
     clique troca a foto via $wire.panoramaDados() SEM fechar o modal. yaw da seta = bearing real −
     azimute da captura; northOffset = azimute (bússola correta).
     `dados.url` vem do accessor PontoPanoramico::imagem_url (URL ASSINADA do bucket R2) — nunca
     asset('storage/…'): as fotos em massa não estão no disco da VPS (bug corrigido nesta data).
     Sem foto = aviso; no público NÃO há imagem de demonstração.
     Estilos inline: o CSS do painel cidadão não tem as classes de cor do Tailwind. --}}
@if ($ponto && $dados)
    <div style="display:flex; flex-direction:column; gap:8px;"
        x-data="{
            atual: @js($dados),
            aviso: null,
            carregando: false,
            initPannellum() {
                if (! this.atual.url) return;
                if (typeof pannellum === 'undefined') {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css';
                    document.head.appendChild(link);

                    const script = document.createElement('script');
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
                    if (window._panoViewerPublico) {
                        try { window._panoViewerPublico.destroy(); } catch (e) {}
                        window._panoViewerPublico = null;
                    }
                    window._panoViewerPublico = pannellum.viewer(@js($uniqueId), {
                        type: 'equirectangular',
                        panorama: this.atual.url,
                        autoLoad: true,
                        compass: true,
                        northOffset: this.atual.azimuth || 0,
                        showControls: true,
                        mouseZoom: true,
                        hotSpots: this.setasHotspots()
                    });
                }, 200);
            },
            async navegar(id) {
                if (this.carregando) return;
                this.aviso = null;
                this.carregando = true;
                try {
                    const d = await this.$wire.call('panoramaDados', id);
                    if (! d || ! d.url) {
                        this.aviso = 'A foto do próximo ponto ainda não está disponível.';
                        return;
                    }
                    this.atual = d;
                    this.renderizarPanorama();
                } catch (e) {
                    this.aviso = 'Não foi possível carregar o próximo ponto. Tente novamente.';
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

        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
            <div style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700; color:#1f2937; min-width:0;">
                <x-heroicon-o-camera style="width:20px; height:20px; color:#3b82f6; flex-shrink:0;" />
                <span x-text="atual.titulo" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $ponto->titulo }}</span>
            </div>
            <span x-show="carregando" x-cloak style="font-size:11px; color:#6b7280;">carregando…</span>
        </div>

        <div x-show="aviso" x-cloak x-text="aviso"
            style="padding:8px 12px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:700; border:1px solid #fde68a; border-radius:8px;"></div>

        @if ($dados['url'])
            {{-- Container protegido do Livewire: o Pannellum controla o canvas sozinho --}}
            <div wire:ignore>
                <div id="{{ $uniqueId }}"
                    style="width:100%; height:65vh; min-height:460px; border-radius:12px; overflow:hidden; border:1px solid #d1d5db; background:#e5e7eb;"></div>
            </div>

            <p x-show="(atual.setas || []).length > 0" style="font-size:11px; color:#9ca3af; margin:0;">
                &#128663; Clique nas setas dentro da imagem para avançar pela rua.
            </p>
        @else
            {{-- Ponto cadastrado, mas a foto ainda não subiu para o bucket (ou sem arquivo) --}}
            <div style="height:320px; border-radius:12px; border:1px dashed #d1d5db; background:#f9fafb; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; text-align:center; padding:24px;">
                <div style="font-size:34px; line-height:1;">&#128228;</div>
                <div style="font-size:15px; font-weight:700; color:#374151;">Foto ainda não disponível</div>
                <div style="font-size:12px; color:#6b7280; max-width:420px;">A imagem 360º deste ponto ainda não foi publicada pela prefeitura. Tente outro ponto do mapa.</div>
            </div>
        @endif
    </div>
@else
    <div style="height:320px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:#6b7280;">
        <x-heroicon-o-exclamation-triangle style="width:44px; height:44px; color:#9ca3af;" />
        <p style="margin:0;">Ponto panorâmico não encontrado.</p>
    </div>
@endif
