<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cidadão — {{ $tenant->name }}</title>
    <meta property="og:title" content="Portal do Cidadão — {{ $tenant->name }}">
    <meta property="og:description" content="Acesse os serviços digitais da {{ $tenant->name }}: solicitações, cadastro e mapa do município.">
    @if($logoUrl)
        <meta property="og:image" content="{{ $logoUrl }}">
        <link rel="icon" href="{{ $logoUrl }}">
    @endif
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, {{ $brandColor }} 0%, {{ $brandColorDark }} 100%);
            color: #111827;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            padding: 36px 32px;
            max-width: 680px;
            width: 100%;
        }
        .header { text-align: center; margin-bottom: 28px; }
        .header img.brasao {
            height: 84px; max-width: 160px; object-fit: contain;
            margin-bottom: 12px;
        }
        h1 { font-size: 22px; margin: 0 0 4px; color: #1f2937; }
        .sub { color: #6b7280; margin: 0; font-size: 14px; line-height: 1.5; }

        .botoes {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-top: 8px;
        }
        @media (min-width: 640px) {
            .botoes { grid-template-columns: 1fr 1fr; }
        }
        a.botao {
            display: flex; align-items: center; gap: 16px;
            padding: 20px 18px;
            border: 1px solid #e5e7eb; border-radius: 16px;
            text-decoration: none; color: #111827;
            background: #ffffff;
            transition: transform .12s, box-shadow .12s, border-color .12s;
        }
        a.botao:hover, a.botao:focus-visible {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0,0,0,.10);
            border-color: {{ $brandColor }};
            outline: none;
        }
        a.botao .icone {
            flex: 0 0 auto;
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            background: #f3f4f6;
        }
        a.botao.primario { background: {{ $brandColor }}; border-color: {{ $brandColor }}; color: #ffffff; }
        a.botao.primario .icone { background: rgba(255,255,255,.18); }
        a.botao.primario .desc { color: rgba(255,255,255,.85); }
        a.botao .titulo { font-weight: 700; font-size: 16px; margin: 0 0 2px; }
        a.botao .desc { font-size: 13px; color: #6b7280; margin: 0; line-height: 1.4; }

        .footer { text-align: center; margin-top: 26px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Brasão — {{ $tenant->name }}" class="brasao">
            @endif
            <h1>{{ $tenant->name }}</h1>
            <p class="sub">Portal do Cidadão — serviços digitais da prefeitura</p>
        </div>

        <div class="botoes">
            <a class="botao primario" href="{{ url('/cidadao/login?prefeitura='.$tenant->slug) }}">
                <span class="icone">🔑</span>
                <span>
                    <span class="titulo" style="display:block;">Entrar</span>
                    <span class="desc" style="display:block;">Já tenho cadastro — acessar meus processos e solicitações</span>
                </span>
            </a>

            <a class="botao" href="{{ url('/cidadao/register?prefeitura='.$tenant->slug) }}">
                <span class="icone">📝</span>
                <span>
                    <span class="titulo" style="display:block;">Criar cadastro</span>
                    <span class="desc" style="display:block;">Primeiro acesso — cadastre-se para abrir solicitações</span>
                </span>
            </a>

            <a class="botao" href="{{ url('/cidadao/mapa-publico?t='.$tenant->slug) }}">
                <span class="icone">🗺️</span>
                <span>
                    <span class="titulo" style="display:block;">Mapa do município</span>
                    <span class="desc" style="display:block;">Consulte imóveis, zoneamento e viabilidade sem cadastro</span>
                </span>
            </a>

            @if($videoUrl)
                <a class="botao" href="{{ $videoUrl }}" target="_blank" rel="noopener">
                    <span class="icone">🎬</span>
                    <span>
                        <span class="titulo" style="display:block;">Como abrir uma solicitação</span>
                        <span class="desc" style="display:block;">Vídeo passo a passo para o cidadão ou responsável técnico</span>
                    </span>
                </a>
            @endif
        </div>

        <div class="footer">SIGWEB — Sistema de Gestão Municipal</div>
    </div>
</body>
</html>
