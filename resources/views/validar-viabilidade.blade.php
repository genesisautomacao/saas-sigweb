<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Documento {{ $emissao?->protocolo ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: #f3f4f6; margin: 0; padding: 40px 16px; color: #111827; }
        .card { max-width: 640px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.08); padding: 32px; border: 1px solid #e5e7eb; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        .sub { color: #6b7280; margin: 0 0 24px; font-size: 14px; }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .row:last-child { border-bottom: none; }
        .row span:first-child { color: #6b7280; }
        .row span:last-child { font-weight: 600; color: #111827; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge.ok { background: #d1fae5; color: #065f46; }
        .badge.no { background: #fee2e2; color: #991b1b; }
        .badge.warn { background: #fef3c7; color: #92400e; }
        .status-permitido { background: #d1fae5; color: #065f46; }
        .status-permissivel { background: #fef3c7; color: #92400e; }
        .status-proibido { background: #fee2e2; color: #991b1b; }
        h2 { font-size: 14px; color: #374151; margin: 24px 0 8px; }
        .footer { text-align: center; margin-top: 24px; color: #9ca3af; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        @if(!$emissao)
            <h1>Documento não encontrado</h1>
            <p class="sub">O código informado não corresponde a nenhum documento emitido por este sistema.</p>
            <div class="row"><span>Status</span><span class="badge no">INVÁLIDO</span></div>
        @else
            <h1>Documento autêntico</h1>
            <p class="sub">Emitido eletronicamente pelo Sistema SIGWEB — {{ $emissao->tenant?->name ?? 'Município' }}</p>

            <div class="row"><span>Status</span><span class="badge ok">VÁLIDO</span></div>
            <div class="row"><span>Código</span><span>{{ $emissao->protocolo }}</span></div>
            <div class="row"><span>Tipo</span><span>{{ ucfirst($emissao->tipo) }}</span></div>

            @if($emissao->status)
                @php
                    $statusLabel = strtolower($emissao->status);
                    $statusClass = match($statusLabel) {
                        'permitido', 'aprovado' => 'status-permitido',
                        'permissivel', 'permissível' => 'status-permissivel',
                        'proibido', 'reprovado' => 'status-proibido',
                        default => 'badge',
                    };
                @endphp
                <div class="row"><span>Resultado</span><span class="badge {{ $statusClass }}">{{ strtoupper($emissao->status) }}</span></div>
            @endif

            @if($emissao->numero_lote)
                <div class="row"><span>Lote nº</span><span>{{ $emissao->numero_lote }}</span></div>
            @endif

            @if($emissao->inscricao_imobiliaria)
                <div class="row"><span>Inscrição Imobiliária</span><span>{{ $emissao->inscricao_imobiliaria }}</span></div>
            @endif

            <div class="row"><span>Emitido em</span><span>{{ $emissao->created_at->format('d/m/Y H:i') }}</span></div>

            @if($emissao->emissor)
                <div class="row"><span>Emitido por</span><span>{{ $emissao->emissor->name }}</span></div>
            @endif

            <div class="footer">
                Para qualquer dúvida, entre em contato com a Prefeitura de {{ $emissao->tenant?->name ?? '—' }}.
            </div>
        @endif
    </div>
</body>
</html>
