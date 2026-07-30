<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Produtividade por Região Designada</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        .doc-title { font-size: 13px; font-weight: bold; color: #374151; text-align: center; }
        .doc-sub { font-size: 9px; color: #6b7280; text-align: center; margin-top: 3px; padding-bottom: 6px; border-bottom: 1px solid #d1d5db; }
        .cards { display: table; width: 100%; margin: 10px 0; border-collapse: separate; border-spacing: 4px 0; }
        .card { display: table-cell; width: 16.6%; border: 1px solid #d1d5db; border-radius: 4px; padding: 5px 7px; text-align: center; }
        .card .rot { font-size: 7px; color: #6b7280; text-transform: uppercase; }
        .card .val { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .card.ok  { background: #ecfdf5; border-color: #a7f3d0; }
        .card.ok .val { color: #047857; }
        .card.warn { background: #fffbeb; border-color: #fde68a; }
        .card.warn .val { color: #b45309; }
        .card.bad { background: #fef2f2; border-color: #fecaca; }
        .card.bad .val { color: #b91c1c; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 3px 5px; font-size: 8.5px; }
        table.data th { background: #f3f4f6; text-align: left; font-size: 8px; text-transform: uppercase; color: #4b5563; }
        table.data tr { page-break-inside: avoid; }
        .num { text-align: right; }
        .bar-wrap { width: 70px; height: 6px; background: #e5e7eb; border-radius: 3px; display: inline-block; vertical-align: middle; }
        .bar { display: block; height: 6px; background: #10b981; border-radius: 3px; }
        .pct { display: inline-block; width: 34px; text-align: right; vertical-align: middle; }
        .vazio { text-align: center; color: #9ca3af; font-style: italic; padding: 18px; }
        .doc-footer { text-align: center; font-size: 7.5px; color: #9ca3af; margin-top: 8px; }
    </style>
</head>
<body>

    <div class="doc-title">Produtividade por Região Designada — {{ $tenant?->name ?? 'Município' }}</div>
    <div class="doc-sub">
        Período: {{ $periodo }}
        · Cadastrador: {{ $cadastrador ?? 'Todos' }}
        · {{ count($linhas) }} quadra(s) designada(s)
        · Gerado em {{ $dataHora }}
    </div>

    <div class="cards">
        <div class="card">
            <div class="rot">Quadras</div>
            <div class="val">{{ number_format($resumo['quadras']) }}</div>
        </div>
        <div class="card">
            <div class="rot">Lotes na região</div>
            <div class="val">{{ number_format($resumo['total']) }}</div>
        </div>
        <div class="card ok">
            <div class="rot">Coletados no período</div>
            <div class="val">{{ number_format($resumo['no_periodo']) }}</div>
        </div>
        <div class="card warn">
            <div class="rot">Pendentes</div>
            <div class="val">{{ number_format($resumo['pendentes']) }}</div>
        </div>
        <div class="card bad">
            <div class="rot">Inconformidades</div>
            <div class="val">{{ number_format($resumo['inconformidades']) }}</div>
        </div>
        <div class="card">
            <div class="rot">Cumprido</div>
            <div class="val">{{ $resumo['percentual'] }}%</div>
        </div>
    </div>

    @if (empty($linhas))
        <div class="vazio">Nenhuma região designada no período selecionado.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Quadra</th>
                    <th>Cadastrador</th>
                    <th>Período da atribuição</th>
                    <th class="num">Lotes</th>
                    <th class="num">Coletados no período</th>
                    <th class="num">Coletados (total)</th>
                    <th class="num">Restantes</th>
                    <th class="num">Pendentes</th>
                    <th class="num">Inconf.</th>
                    <th style="width: 120px;">% cumprido</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $l)
                    <tr>
                        <td><strong>{{ $l['quadra_nome'] }}</strong></td>
                        <td>{{ $l['cadastrador'] }}</td>
                        <td>{{ $l['periodo'] }}</td>
                        <td class="num">{{ $l['total'] }}</td>
                        <td class="num">{{ $l['no_periodo'] }}</td>
                        <td class="num">{{ $l['coletados'] }}</td>
                        <td class="num">{{ $l['total'] - $l['coletados'] }}</td>
                        <td class="num">{{ $l['pendentes'] }}</td>
                        <td class="num">{{ $l['inconformidades'] }}</td>
                        <td>
                            <span class="bar-wrap">
                                <span class="bar" style="width: {{ min($l['percentual'], 100) }}%;"></span>
                            </span>
                            <span class="pct">{{ $l['percentual'] }}%</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="doc-footer">
        {{ $tenant?->name ?? 'Município' }} — SIGWEB · Relatório gerado em {{ $dataHora }}
    </div>

</body>
</html>
