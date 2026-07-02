<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #374151; font-size: 12px; margin: 0; }
        .header { border-bottom: 1px solid #d1d5db; padding-bottom: 8px; margin-bottom: 14px; }
        .header table { width: 100%; }
        .header-logo { height: 52px; }
        .header-title { font-size: 15px; font-weight: bold; color: #111827; }
        .header-sub { font-size: 10px; color: #9ca3af; }
        .doc-title { font-size: 14px; font-weight: bold; color: #111827; margin: 4px 0 2px; }
        .doc-proto { font-size: 10px; color: #9ca3af; margin-bottom: 10px; }
        .section-title { color: #6b7280; font-size: 10px; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.05em; padding-bottom: 3px; margin: 16px 0 6px; border-bottom: 1px solid #e5e7eb; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data td { padding: 3px 2px; vertical-align: top; }
        table.data td.k { color: #9ca3af; width: 34%; }
        table.data td.v { color: #111827; }
        .etapa { margin-bottom: 8px; }
        .etapa-nome { font-weight: bold; color: #374151; margin-bottom: 2px; }
        ul { margin: 3px 0; padding-left: 16px; }
        li { margin-bottom: 2px; }
        a { color: #1d4ed8; text-decoration: underline; }
        table.hist { width: 100%; border-collapse: collapse; }
        table.hist th { text-align: left; padding: 4px 4px; font-size: 10px; color: #9ca3af;
            text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #d1d5db; }
        table.hist td { padding: 4px 4px; border-bottom: 1px solid #f3f4f6; font-size: 11px; vertical-align: top; }
        .muted { color: #9ca3af; font-style: italic; }
        .footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="width:70px;">
                    @if(isset($tenant->data['logo']))
                        <img src="{{ public_path('storage/' . $tenant->data['logo']) }}" class="header-logo">
                    @else
                        <img src="{{ public_path('assets/images/logo-login.png') }}" alt="Logo" class="header-logo">
                    @endif
                </td>
                <td>
                    <div class="header-title">{{ $tenant->name ?? 'Prefeitura Municipal' }}</div>
                    <div class="header-sub">Processo Digital — Documento de acompanhamento</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">{{ $processo->fluxo->nome ?? 'Processo Digital' }}</div>
    <div class="doc-proto">Protocolo <strong>{{ $processo->codigo_processo }}</strong> · Emitido em {{ $dataHora }}</div>

    <div class="section-title">Dados Gerais</div>
    <table class="data">
        <tr><td class="k">Serviço</td><td class="v">{{ $processo->fluxo->nome ?? '—' }}</td></tr>
        <tr><td class="k">Fase Atual</td><td class="v">{{ $processo->etapaAtual->nome ?? 'Aguardando triagem' }}</td></tr>
        <tr><td class="k">Situação</td><td class="v">{{ $statusLabel }}</td></tr>
        <tr><td class="k">Aberto em</td><td class="v">{{ $processo->created_at?->format('d/m/Y H:i') }}</td></tr>
    </table>

    <div class="section-title">Solicitante</div>
    <table class="data">
        <tr><td class="k">Nome</td><td class="v">{{ $requerente['nome'] ?: '—' }}</td></tr>
        <tr><td class="k">E-mail</td><td class="v">{{ $requerente['email'] ?: '—' }}</td></tr>
        <tr><td class="k">CPF</td><td class="v">{{ $requerente['cpf'] ?: '—' }}</td></tr>
        <tr><td class="k">Telefone</td><td class="v">{{ $requerente['telefone'] ?: '—' }}</td></tr>
    </table>

    @if($imovel)
        <div class="section-title">Imóvel Vinculado</div>
        <table class="data">
            <tr><td class="k">Número do Lote</td><td class="v">{{ $imovel['numero_lote'] ?: '—' }}</td></tr>
            <tr><td class="k">Quadra</td><td class="v">{{ $imovel['quadra'] ?: '—' }}</td></tr>
            <tr><td class="k">Loteamento</td><td class="v">{{ $imovel['loteamento'] ?: '—' }}</td></tr>
            <tr><td class="k">Localização</td><td class="v">{{ $imovel['localizacao'] ?: '—' }}</td></tr>
            @foreach($imovel['unidades'] as $u)
                <tr><td class="k">Inscrição Imobiliária</td><td class="v">{{ $u['inscricao'] ?: '—' }}</td></tr>
                <tr><td class="k">Cadastro Imobiliário</td><td class="v">{{ $u['cadastro'] ?: '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="section-title">Respostas do Formulário</div>
    @forelse($respostas as $bloco)
        <div class="etapa">
            <div class="etapa-nome">{{ $bloco['etapa'] }}</div>
            <ul>
                @foreach($bloco['itens'] as $item)
                    <li><strong>{{ $item['label'] }}:</strong> {{ $item['valor'] !== '' ? $item['valor'] : '—' }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="muted">Nenhuma resposta registrada.</div>
    @endforelse

    <div class="section-title">Documentos Anexados</div>
    @if(count($documentos))
        <ul>
            @foreach($documentos as $doc)
                <li>
                    @if(!empty($doc['url']))
                        <a href="{{ $doc['url'] }}">{{ $doc['nome'] }}</a>
                    @else
                        {{ $doc['nome'] }}
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <div class="muted">Nenhum documento anexado.</div>
    @endif

    <div class="section-title">Histórico de Tramitação</div>
    @if(count($historico))
        <table class="hist">
            <thead>
                <tr>
                    <th style="width:80px;">Data</th>
                    <th style="width:80px;">Decisão</th>
                    <th>Movimentação</th>
                    <th style="width:110px;">Responsável</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historico as $h)
                    <tr>
                        <td>{{ $h['data'] }}</td>
                        <td>{{ $h['decisao'] }}</td>
                        <td>
                            {{ $h['origem'] }} &rarr; {{ $h['destino'] }}
                            @if($h['parecer'])<br><span class="muted">{{ $h['parecer'] }}</span>@endif
                        </td>
                        <td>{{ $h['usuario'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="muted">Sem tramitações registradas.</div>
    @endif

    <div class="footer">
        Documento gerado eletronicamente pelo SIGWEB em {{ $dataHora }} · Protocolo {{ $processo->codigo_processo }}
    </div>
</body>
</html>
