{{-- R80-1 — Cloudflare Turnstile ("não sou robô", quase sempre sem clique). Só é
     renderizado quando o ApiSetting "Cloudflare Turnstile" existe. O token vai para o
     campo oculto turnstile_token do formulário do "Fale conosco". --}}
<div wire:ignore x-data x-init="
    const montar = () => window.turnstile.render($refs.caixa, {
        sitekey: @js($siteKey),
        language: 'pt-br',
        callback: (token) => $wire.set('mountedActionsData.0.turnstile_token', token, false),
        'expired-callback': () => $wire.set('mountedActionsData.0.turnstile_token', null, false),
    });
    if (window.turnstile) { montar(); }
    else {
        const s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        s.async = true;
        s.onload = montar;
        document.head.appendChild(s);
    }
">
    <div x-ref="caixa"></div>
</div>
