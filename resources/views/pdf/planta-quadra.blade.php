<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Planta de Quadra — {{ $quadra->name }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }

        .doc-header { border-bottom: 2.5px solid #1f2937; padding-bottom: 8px; margin-bottom: 10px; overflow: hidden; }
        .doc-header .title { font-size: 16px; font-weight: bold; color: #111827; margin: 0 0 2px 0; }
        .doc-header .sub { font-size: 10px; color: #6b7280; }
        .doc-header .right { float: right; text-align: right; font-size: 9px; color: #6b7280; }

        .layout { display: table; width: 100%; margin-bottom: 8px; }
        .layout .col { display: table-cell; vertical-align: top; padding-right: 8px; }
        .col-croqui { width: 55%; }
        .col-info { width: 45%; padding-left: 8px; padding-right: 0; }

        .croqui-box { border: 1.5px solid #1f2937; border-radius: 4px; padding: 4px; background: #f9fafb; }
        .croqui-box img { width: 100%; height: 320px; object-fit: contain; display: block; background: white; border-radius: 3px; }
        .croqui-placeholder { width: 100%; height: 320px; background: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 3px; display: table-cell; vertical-align: middle; text-align: center; color: #9ca3af; font-style: italic; }

        .info-block { border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; }
        .info-block h3 { margin: 0 0 4px 0; font-size: 10px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 2px; }
        .info-block .row { padding: 2px 0; font-size: 9.5px; }
        .info-block .row strong { display: inline-block; min-width: 105px; color: #4b5563; }

        .summary { display: table; width: 100%; margin-top: 4px; }
        .summary .cell { display: table-cell; width: 25%; padding: 6px 4px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; text-align: center; }
        .summary .cell + .cell { margin-left: 4px; }
        .summary .label { font-size: 8px; color: #1e40af; text-transform: uppercase; letter-spacing: 0.3px; }
        .summary .value { font-size: 13px; font-weight: bold; color: #1e3a8a; margin-top: 2px; }

        h2.section-title { font-size: 12px; color: #111827; margin: 14px 0 6px 0; padding-bottom: 3px; border-bottom: 1.5px solid #d1d5db; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 3px 5px; font-size: 9px; text-align: left; }
        table.data th { background: #1f2937; color: white; font-weight: bold; font-size: 8.5px; text-transform: uppercase; }
        table.data tr:nth-child(even) td { background: #f9fafb; }
        table.data .num { text-align: right; font-variant-numeric: tabular-nums; }
        table.data .ctr { text-align: center; }

        .badge { display: inline-block; padding: 1px 5px; border-radius: 6px; font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
        .badge-construido { background: #d1fae5; color: #065f46; }
        .badge-baldio { background: #fef3c7; color: #92400e; }
        .badge-default { background: #f3f4f6; color: #4b5563; }

        .lote-card { page-break-inside: avoid; margin-top: 8px; border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 8px; }
        .lote-card .lote-title { font-weight: bold; font-size: 10px; color: #1f2937; margin-bottom: 3px; padding-bottom: 2px; border-bottom: 1px solid #e5e7eb; }
        .lote-card .sub-table th { background: #4b5563; }

        .doc-footer { position: fixed; bottom: 4mm; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #9ca3af; }

        .empty { padding: 6px 8px; color: #9ca3af; font-style: italic; font-size: 9px; background: #f9fafb; border-radius: 3px; }
    </style>
</head>
<body>

    {{-- CABEÇALHO --}}
    <div class="doc-header">
        <div class="right">
            <div>Gerado em {{ $dataHora }}</div>
            <div>{{ $tenant?->name ?? '—' }}</div>
        </div>
        <h1 class="title">Planta de Quadra — {{ $quadra->name }}</h1>
        <div class="sub">
            {{ $quadra->bairro?->name ? 'Bairro ' . $quadra->bairro->name . ' · ' : '' }}
            {{ $quadra->loteamento?->name ? 'Loteamento ' . $quadra->loteamento->name . ' · ' : '' }}
            {{ $quadra->perimetro?->name ? 'Distrito ' . $quadra->perimetro->name : '' }}
            @if($quadra->setor_codigo) · Setor {{ $quadra->setor_codigo }} @endif
        </div>
    </div>

    {{-- PRIMEIRA SEÇÃO: CROQUI + INFO + RESUMO --}}
    <div class="layout">
        {{-- COLUNA ESQUERDA: CROQUI --}}
        <div class="col col-croqui">
            <div class="croqui-box">
                @if($mapImageBase64)
                    <img src="{{ $mapImageBase64 }}" alt="Croqui da Quadra">
                @else
                    <div style="display: table; width: 100%; height: 320px;">
                        <div class="croqui-placeholder">Croqui não disponível — gere a partir do mapa para incluir a imagem.</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- COLUNA DIREITA: IDENTIFICAÇÃO + RESUMO --}}
        <div class="col col-info">
            <div class="info-block">
                <h3>Identificação</h3>
                <div class="row"><strong>Quadra:</strong> {{ $quadra->name }}</div>
                <div class="row"><strong>ID Sequencial:</strong> #{{ $quadra->sequential_id }}</div>
                <div class="row"><strong>Bairro:</strong> {{ $quadra->bairro?->name ?? '—' }}</div>
                <div class="row"><strong>Loteamento:</strong> {{ $quadra->loteamento?->name ?? '—' }}</div>
                <div class="row"><strong>Distrito:</strong> {{ $quadra->perimetro?->name ?? '—' }}</div>
                <div class="row"><strong>Cód. Setor:</strong> {{ $quadra->setor_codigo ?? '—' }}</div>
                <div class="row"><strong>Área Total da Quadra:</strong>
                    {{ $quadra->area_geo ? number_format($quadra->area_geo, 2, ',', '.') . ' m²' : '—' }}
                </div>
            </div>

            <div class="summary">
                <div class="cell">
                    <div class="label">Total Lotes</div>
                    <div class="value">{{ $totalLotes }}</div>
                </div>
                <div class="cell">
                    <div class="label">Construídos</div>
                    <div class="value">{{ $totalConstruidos }}</div>
                </div>
                <div class="cell">
                    <div class="label">Baldios</div>
                    <div class="value">{{ $totalBaldios }}</div>
                </div>
                <div class="cell">
                    <div class="label">Edificações</div>
                    <div class="value">{{ $totalEdificacoes }}</div>
                </div>
            </div>

            <div class="summary" style="margin-top: 4px;">
                <div class="cell" style="width: 33.33%; background: #eff6ff; border-color: #bfdbfe;">
                    <div class="label" style="color: #1e40af;">Área da quadra</div>
                    <div class="value" style="color: #1e3a8a;">
                        {{ $quadra->area_geo ? number_format($quadra->area_geo, 2, ',', '.') . ' m²' : '—' }}
                    </div>
                </div>
                <div class="cell" style="width: 33.33%; background: #f0fdf4; border-color: #bbf7d0;">
                    <div class="label" style="color: #166534;">Área total dos lotes</div>
                    <div class="value" style="color: #14532d;">{{ number_format($areaTotalLotes, 2, ',', '.') }} m²</div>
                </div>
                <div class="cell" style="width: 33.33%; background: #fef2f2; border-color: #fecaca;">
                    <div class="label" style="color: #991b1b;">Área construída</div>
                    <div class="value" style="color: #7f1d1d;">{{ number_format($areaTotalConstr, 2, ',', '.') }} m²</div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABELA RESUMO DE LOTES --}}
    <h2 class="section-title">Lotes da Quadra ({{ $totalLotes }})</h2>

    @if($lotes->isEmpty())
        <div class="empty">Nenhum lote cadastrado nesta quadra.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th class="ctr" style="width: 5%;">Lote nº</th>
                    <th style="width: 18%;">Inscrição</th>
                    <th style="width: 22%;">Endereço</th>
                    <th class="num" style="width: 10%;">Área (m²)</th>
                    <th class="num" style="width: 9%;">Testada (m)</th>
                    <th class="ctr" style="width: 10%;">Ocupação</th>
                    <th class="ctr" style="width: 9%;">Situação</th>
                    <th class="ctr" style="width: 8%;">Edif.</th>
                    <th class="num" style="width: 9%;">Área Constr.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lotes as $l)
                    @php
                        $primeiraUnidade = $l->unidadesImobiliarias->first();
                        $ocupacaoLabel = ['baldio' => 'Baldio', 'construido' => 'Construído'][$l->ocupacao] ?? '—';
                        $ocupacaoClass = ['baldio' => 'badge-baldio', 'construido' => 'badge-construido'][$l->ocupacao] ?? 'badge-default';
                        $situacaoLabel = ['meio_quadra' => 'Meio', 'esquina' => 'Esquina', 'encravado' => 'Encravado'][$l->situacao_quadra] ?? '—';
                        $areaConstrLote = $l->edificacoes->sum('area_geo');
                    @endphp
                    <tr>
                        <td class="ctr"><strong>{{ $l->numero_lote ?? '—' }}</strong></td>
                        <td>{{ $primeiraUnidade?->inscricao_imobiliaria ?? '—' }}</td>
                        <td>
                            {{ trim(($primeiraUnidade?->logradouro_nome ?? '') . ' ' . ($primeiraUnidade?->numero_imovel ?? '')) ?: '—' }}
                        </td>
                        <td class="num">{{ number_format($l->area_geo ?? 0, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($l->main_facade_length ?? 0, 2, ',', '.') }}</td>
                        <td class="ctr"><span class="badge {{ $ocupacaoClass }}">{{ $ocupacaoLabel }}</span></td>
                        <td class="ctr">{{ $situacaoLabel }}</td>
                        <td class="ctr">{{ $l->edificacoes->count() }}</td>
                        <td class="num">{{ number_format($areaConstrLote, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- DETALHAMENTO DE EDIFICAÇÕES POR LOTE --}}
    @foreach($lotes as $l)
        @continue($l->edificacoes->isEmpty() && $l->unidadesImobiliarias->isEmpty())

        <div class="lote-card">
            <div class="lote-title">
                Lote {{ $l->numero_lote ?? '—' }}
                @if($l->zona)
                    <span style="font-weight: normal; color: #6b7280; font-size: 9px;">· Zona {{ $l->zona->sigla }}</span>
                @endif
            </div>

            @if($l->unidadesImobiliarias->isNotEmpty())
                <div style="font-size: 9px; font-weight: bold; color: #4b5563; margin: 3px 0 2px 0;">Unidades Imobiliárias</div>
                <table class="data sub-table">
                    <thead>
                        <tr>
                            <th>Inscrição</th>
                            <th>Cód. Tributário</th>
                            <th>Logradouro</th>
                            <th class="ctr">Nº</th>
                            <th>Complemento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($l->unidadesImobiliarias as $u)
                            <tr>
                                <td>{{ $u->inscricao_imobiliaria ?? '—' }}</td>
                                <td>{{ $u->codigo_imovel_tributario ?? '—' }}</td>
                                <td>{{ $u->logradouro_nome ?? '—' }}</td>
                                <td class="ctr">{{ $u->numero_imovel ?? '—' }}</td>
                                <td>{{ $u->complemento ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($l->edificacoes->isNotEmpty())
                <div style="font-size: 9px; font-weight: bold; color: #4b5563; margin: 4px 0 2px 0;">Edificações</div>
                <table class="data sub-table">
                    <thead>
                        <tr>
                            <th>Finalidade / Uso</th>
                            <th>Material</th>
                            <th>Característica</th>
                            <th class="ctr">Conservação</th>
                            <th class="ctr">Pav.</th>
                            <th class="num">Área (m²)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($l->edificacoes as $e)
                            <tr>
                                <td>{{ $e->tipo ?? '—' }}</td>
                                <td>{{ $e->tp_construcao ?? '—' }}</td>
                                <td>{{ $e->caracteristica_construcao ?? '—' }}</td>
                                <td class="ctr">{{ $e->estado_conservacao ?? '—' }}</td>
                                <td class="ctr">{{ $e->pavimento ?? '—' }}</td>
                                <td class="num">{{ number_format($e->area_geo ?? 0, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="doc-footer">
        {{ $tenant?->name ?? 'Município' }} · Planta de Quadra {{ $quadra->name }} · Gerado em {{ $dataHora }} · Sistema SIGWEB
    </div>

</body>
</html>
