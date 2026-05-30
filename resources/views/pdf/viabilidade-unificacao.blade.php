<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Laudo de Unificação de Lotes</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { border-bottom: 2px solid #444; padding-bottom: 10px; margin-bottom: 15px; }
        .logo { max-height: 60px; float: left; margin-right: 15px; }
        .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table th, .info-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .status { padding: 4px 8px; border-radius: 3px; font-weight: bold; color: #fff; }
    </style>
</head>
<body>
    <div class="header">
        @if($tenant && $tenant->logo_path)
            <img src="{{ public_path('storage/' . $tenant->logo_path) }}" class="logo">
        @endif
        <div style="float: left;">
            <div style="font-size: 14px; font-weight: bold;">ESTUDO DE VIABILIDADE: UNIFICAÇÃO (REMEMBRAMENTO)</div>
            <div style="font-size: 10px; color: #666;">Protocolo: {{ $protocolo }} | Emissão: {{ $dataHora }}</div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="box">
        <h3>Dados da Unificação</h3>
        <table class="info-table">
            <tr>
                <th>Lotes Envolvidos:</th><td>{{ $dadosAnalise['lotes_envolvidos'] }}</td>
            </tr>
            <tr>
                <th>Zona de Uso:</th><td>{{ $dadosAnalise['zona']['sigla'] }} - {{ $dadosAnalise['zona']['nome'] }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <table class="info-table" style="text-align: center;">
            <tr>
                <td><strong>Qtd. Lotes</strong><br>{{ $dadosAnalise['qtd_lotes'] }}</td>
                <td><strong>Área Total Resultante</strong><br>{{ number_format($dadosAnalise['area_resultante_m2'], 2, ',', '.') }} m²</td>
                <td><strong>Testada Total</strong><br>{{ number_format($dadosAnalise['testada_resultante_m'], 2, ',', '.') }} m</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h3>Parecer Técnico</h3>
        @foreach($dadosAnalise['parecer_tecnico'] as $p)
            <p style="margin: 5px 0; border-left: 3px solid #3b82f6; padding-left: 10px;">{{ $p }}</p>
        @endforeach
        <div style="margin-top: 15px;">
            Veredito: 
            @php $st = $dadosAnalise['status_final']; $bg = $st == 'permitido' ? '#10b981' : ($st == 'permissivel' ? '#f59e0b' : '#ef4444'); @endphp
            <span class="status" style="background: {{ $bg }}">{{ strtoupper($st) }}</span>
        </div>
    </div>

    @if($mapImageBase64)
        <div class="box" style="text-align: center;">
            <h3>Croqui de Unificação</h3>
            <img src="{{ $mapImageBase64 }}" style="max-width: 100%; border: 1px solid #999;">
        </div>
    @endif

    {{-- BLOCO DE AUTENTICAÇÃO (TR Tangará #21 / #14) --}}
    <div style="margin-top: 18px; padding: 10px; border: 1.5px dashed #1f2937; border-radius: 4px; font-size: 10px; line-height: 1.5; background: #f9fafb;">
        <div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">Autenticação do Documento</div>
        <div><strong>Código:</strong> {{ $protocolo }}</div>
        <div><strong>Validar em:</strong> {{ $urlValidacao ?? url('/v/'.$protocolo) }}</div>
        <div style="color: #6b7280; margin-top: 4px;">Este documento foi emitido eletronicamente. Para conferir sua autenticidade, acesse o endereço acima ou informe o código no portal da Prefeitura.</div>
    </div>
</body>
</html>