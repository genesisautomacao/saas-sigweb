{{-- Player da câmera de monitoramento (docs/piuma.txt, Onda 5). Renderizado como
     modalContent do opcoesMobCameraAction — o Filament destrói o modal ao fechar,
     então o stream para sozinho. Estilos INLINE (a página só tem o CSS
     pré-compilado do Filament).
     ⚠️ wire:ignore no player: o Livewire nunca deve tocar no iframe/<video>
     (qualquer morph recriaria o player e recomeçaria o stream).
     ⚠️ Contexto seguro: players de terceiros (FullCam) usam crypto.randomUUID,
     que só existe em HTTPS — sob uma página HTTP (Laragon local) o app deles
     cai em loop de recarga ("pisca"). Nesse caso mostramos o aviso + "Abrir em
     nova aba" em vez do iframe; em produção (HTTPS) o vídeo abre aqui mesmo. --}}
@php
    $src = $camera->urlPlayer();
    $tipo = $camera->tipo;
    $uid = 'mobcam-'.$camera->id;
    $sep = str_contains($src, '?') ? '&' : '?';
@endphp
<div style="display:flex; flex-direction:column; gap:8px;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#374151;">
            <span style="display:inline-flex; align-items:center; gap:6px; background:#dc2626; color:#fff; font-weight:800; font-size:11px; padding:2px 8px; border-radius:999px; letter-spacing:.04em;">
                <span style="width:7px; height:7px; border-radius:50%; background:#fff; display:inline-block;"></span> AO VIVO
            </span>
            @if ($camera->provedor)
                <span>Transmissão: <strong>{{ $camera->provedor }}</strong></span>
            @endif
            @if ($camera->azimute_visada !== null)
                <span>· visada {{ number_format((float) $camera->azimute_visada, 0) }}°</span>
            @endif
            @unless ($camera->ativo)
                <span style="color:#b45309; font-weight:700;">· câmera marcada como inativa</span>
            @endunless
        </div>
        <a href="{{ $src }}" target="_blank" rel="noopener" style="font-size:12px; color:#2563eb; font-weight:600;">Abrir em nova aba &#8599;</a>
    </div>

    <div wire:ignore style="position:relative; width:100%; padding-top:56.25%; background:#000; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px -12px rgba(0,0,0,.5);">
        @if ($tipo === 'imagem')
            <img src="{{ $src }}" alt="{{ $camera->nome }}"
                style="position:absolute; inset:0; width:100%; height:100%; object-fit:contain;"
                x-data x-init="const base = @js($src); const sep = @js($sep); setInterval(() => { $el.src = base + sep + '_t=' + Date.now(); }, 5000)">
        @elseif ($tipo === 'hls')
            <video id="{{ $uid }}" controls autoplay muted playsinline
                style="position:absolute; inset:0; width:100%; height:100%; background:#000;"></video>
            <div x-data x-init="
                const src = @js($src);
                const video = document.getElementById(@js($uid));
                const iniciar = () => {
                    if (window.Hls && window.Hls.isSupported()) {
                        const hls = new window.Hls({ lowLatencyMode: true });
                        hls.loadSource(src); hls.attachMedia(video);
                        hls.on(window.Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                        video.src = src; video.play().catch(() => {});
                    }
                };
                if (window.Hls) { iniciar(); } else {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js';
                    s.onload = iniciar; document.head.appendChild(s);
                }
            "></div>
        @else
            {{-- embed / youtube: só monta o iframe em contexto seguro (ou se o usuário forçar) --}}
            <div x-data="{ seguro: window.isSecureContext === true, forcar: false }" style="position:absolute; inset:0;">
                <template x-if="seguro || forcar">
                    <iframe src="{{ $src }}" title="{{ $camera->nome }}"
                        allow="autoplay; fullscreen; picture-in-picture" allowfullscreen
                        style="position:absolute; inset:0; width:100%; height:100%; border:0;"></iframe>
                </template>
                <template x-if="!seguro && !forcar">
                    <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:24px; text-align:center; color:#e5e7eb;">
                        <div style="font-size:30px; line-height:1;">&#128274;</div>
                        <div style="font-weight:700; font-size:15px;">Player dispon&iacute;vel apenas em HTTPS</div>
                        <div style="font-size:12px; color:#9ca3af; max-width:460px; line-height:1.5;">
                            Esta p&aacute;gina est&aacute; em HTTP (ambiente local). O provedor do v&iacute;deo exige contexto seguro;
                            em produ&ccedil;&atilde;o (https) a transmiss&atilde;o abre aqui mesmo.
                        </div>
                        <a href="{{ $src }}" target="_blank" rel="noopener"
                            style="display:inline-flex; align-items:center; gap:6px; background:#dc2626; color:#fff; font-weight:700; font-size:13px; padding:8px 16px; border-radius:999px; text-decoration:none;">
                            Abrir em nova aba &#8599;
                        </a>
                        <button type="button" @click="forcar = true"
                            style="font-size:11px; color:#9ca3af; background:none; border:0; text-decoration:underline; cursor:pointer;">
                            tentar carregar aqui mesmo assim
                        </button>
                    </div>
                </template>
            </div>
        @endif
    </div>

    @if ($camera->descricao)
        <p style="font-size:12px; color:#6b7280; margin:0;">{{ $camera->descricao }}</p>
    @endif
    <p style="font-size:11px; color:#9ca3af; margin:0;">O SIGWEB apenas exibe a transmissão do provedor; nenhuma imagem é gravada aqui.</p>
</div>
