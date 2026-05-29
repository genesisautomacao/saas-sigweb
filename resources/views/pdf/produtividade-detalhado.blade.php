<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Coletas — Produtividade</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        .doc-title { font-size: 12px; font-weight: bold; color: #374151; text-align: center; margin-bottom: 8px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        .lote-card { page-break-inside: avoid; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 10px; margin-bottom: 10px; }
        .header { border-bottom: 1.5px solid #f59e0b; padding-bottom: 4px; margin-bottom: 6px; overflow: hidden; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-weight: bold; font-size: 8px; }
        .badge-coletado       { background: #d1fae5; color: #047857; }
        .badge-pendente       { background: #fef3c7; color: #b45309; }
        .badge-inconformidade { background: #fee2e2; color: #b91c1c; }
        .badge-nao_visitado   { background: #f3f4f6; color: #4b5563; }
        .lote-title { font-size: 13px; font-weight: bold; }
        .lote-sub { color: #6b7280; font-size: 9px; }
        .grid-2 { display: table; width: 100%; }
        .grid-2 .col { display: table-cell; width: 50%; padding: 2px 6px; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 3px; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 2px 4px; text-align: left; font-size: 8.5px; }
        table.data th { background: #f3f4f6; }
        .photos { display: table; width: 100%; margin-top: 5px; }
        .photos .photo { display: table-cell; width: 33%; padding: 2px; vertical-align: top; }
        .photos img { width: 100%; height: 70px; object-fit: cover; border: 1px solid #d1d5db; border-radius: 3px; }
        .photo-label { font-size: 7px; text-align: center; color: #6b7280; margin-top: 1px; }
        .section-title { font-weight: bold; font-size: 9.5px; margin-top: 5px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 1px; }
        .placeholder { height: 70px; background: #f3f4f6; border: 1px dashed #d1d5db; border-radius: 3px; }
        .inconf-box { background: #fef2f2; border-left: 3px solid #ef4444; padding: 4px 8px; margin-top: 4px; font-size: 8.5px; }
        .empty-section { color: #9ca3af; font-style: italic; font-size: 8.5px; }
        .doc-footer { text-align: center; font-size: 7.5px; color: #9ca3af; margin-top: 4px; }
    </style>
</head>
<body>

    <div class="doc-title">
        Relatório de Coletas — {{ $tenant?->name ?? 'Município' }}
        <span style="font-weight: normal; color:#6b7280;">· {{ $lotes->count() }} lote(s) · Gerado em {{ $dataHora }}</span>
    </div>

    @foreach($lotes as $lote)
        @php
            $statusLabel = [
                'nao_visitado'   => 'Não Visitado',
                'coletado'       => 'Coletado',
                'pendente'       => 'Pendente',
                'inconformidade' => 'Inconformidade',
            ][$lote->status_cadastro] ?? '—';

            $ocupacaoLabel = [
                'baldio'     => 'Baldio',
                'construido' => 'Construído',
            ][$lote->ocupacao] ?? '—';

            $situacaoLabel = [
                'meio_quadra' => 'Meio de Quadra',
                'esquina'     => 'Esquina',
                'encravado'   => 'Encravado',
            ][$lote->situacao_quadra] ?? '—';
        @endphp

        <div class="lote-card">
            <div class="header">
                <div style="float:right;">
                    <span class="badge badge-{{ $lote->status_cadastro }}">{{ $statusLabel }}</span>
                </div>
                <div class="lote-title">Lote {{ $lote->numero_lote ?? '—' }}</div>
                <div class="lote-sub">
                    ID #{{ $lote->sequential_id }} ·
                    Coletado por <strong>{{ $lote->coletor?->name ?? '—' }}</strong>
                    @if($lote->coletado_em) em {{ $lote->coletado_em->format('d/m/Y H:i') }} @endif
                </div>
            </div>

            {{-- Dados do lote --}}
            <div class="section-title">Dados do Lote</div>
            <div class="grid-2">
                <div class="col">
                    <strong>Quadra:</strong> {{ $lote->quadra?->name ?? '—' }}<br>
                    <strong>Zona:</strong> {{ $lote->zona?->sigla ?? '—' }}<br>
                    <strong>Ocupação:</strong> {{ $ocupacaoLabel }}<br>
                    <strong>Situação:</strong> {{ $situacaoLabel }}
                </div>
                <div class="col">
                    <strong>Área:</strong> {{ number_format($lote->area_geo ?? 0, 2, ',', '.') }} m²<br>
                    <strong>Testada:</strong> {{ number_format($lote->main_facade_length ?? 0, 2, ',', '.') }} m<br>
                    <strong>Observação:</strong> {{ $lote->observacao ?: '—' }}
                </div>
            </div>

            @if($lote->status_cadastro === 'inconformidade' && $lote->inconformidade_descricao)
                <div class="inconf-box">
                    <strong>Inconformidade:</strong> {{ $lote->inconformidade_descricao }}
                </div>
            @endif

            {{-- Unidades --}}
            <div class="section-title">Unidades Imobiliárias ({{ $lote->unidadesImobiliarias->count() }})</div>
            @if($lote->unidadesImobiliarias->isEmpty())
                <div class="empty-section">Nenhuma unidade cadastrada.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Inscrição</th>
                            <th>Cód. Tributário</th>
                            <th>Logradouro / Nº</th>
                            <th>Proprietário</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lote->unidadesImobiliarias as $u)
                            <tr>
                                <td>{{ $u->inscricao_imobiliaria ?? '—' }}</td>
                                <td>{{ $u->codigo_imovel_tributario ?? '—' }}</td>
                                <td>{{ trim(($u->logradouro_nome ?? '') . ' ' . ($u->numero_imovel ?? '')) ?: '—' }}</td>
                                <td>{{ data_get($u->dados_tributarios, 'proprietario_name', '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Edificações --}}
            <div class="section-title">Edificações ({{ $lote->edificacoes->count() }})</div>
            @if($lote->edificacoes->isEmpty())
                <div class="empty-section">Nenhuma edificação cadastrada.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Finalidade</th>
                            <th>Material</th>
                            <th>Característica</th>
                            <th>Conservação</th>
                            <th>Pav.</th>
                            <th>Área (m²)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lote->edificacoes as $e)
                            <tr>
                                <td>{{ $e->tipo ?? '—' }}</td>
                                <td>{{ $e->tp_construcao ?? '—' }}</td>
                                <td>{{ $e->caracteristica_construcao ?? '—' }}</td>
                                <td>{{ $e->estado_conservacao ?? '—' }}</td>
                                <td style="text-align:center;">{{ $e->pavimento ?? '—' }}</td>
                                <td style="text-align:right;">{{ number_format($e->area_geo ?? 0, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Fotos de campo --}}
            <div class="section-title">Fotos de Campo</div>
            <div class="photos">
                <div class="photo">
                    @if($lote->_fotos_b64['frontal'])
                        <img src="{{ $lote->_fotos_b64['frontal'] }}">
                    @else
                        <div class="placeholder"></div>
                    @endif
                    <div class="photo-label">Frontal</div>
                </div>
                <div class="photo">
                    @if($lote->_fotos_b64['lateral_esq'])
                        <img src="{{ $lote->_fotos_b64['lateral_esq'] }}">
                    @else
                        <div class="placeholder"></div>
                    @endif
                    <div class="photo-label">Lateral Esq.</div>
                </div>
                <div class="photo">
                    @if($lote->_fotos_b64['lateral_dir'])
                        <img src="{{ $lote->_fotos_b64['lateral_dir'] }}">
                    @else
                        <div class="placeholder"></div>
                    @endif
                    <div class="photo-label">Lateral Dir.</div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="doc-footer">
        {{ $tenant?->name ?? 'Município' }} — Relatório gerado em {{ $dataHora }}
    </div>

</body>
</html>
