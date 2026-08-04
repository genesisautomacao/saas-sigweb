<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Validação da Coleta</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1f2937; margin: 18px; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .meta { color: #6b7280; font-size: 9px; margin-bottom: 10px; }
        .carimbo { border: 2px solid #059669; color: #059669; display: inline-block; padding: 4px 10px;
                   font-weight: bold; font-size: 11px; border-radius: 6px; margin: 6px 0; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 8px; }
        .cards td { border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px 8px; text-align: center; width: 16%; }
        .cards .num { font-size: 14px; font-weight: bold; display: block; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th { background: #f3f4f6; text-align: left; padding: 4px 6px; font-size: 8.5px;
                         text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; }
        table.dados td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .badge { padding: 1px 6px; border-radius: 8px; font-size: 8.5px; font-weight: bold; }
        .b-coletado { background: #d1fae5; color: #047857; }
        .b-pendente { background: #fef3c7; color: #b45309; }
        .b-inconformidade { background: #fee2e2; color: #b91c1c; }
        .b-nao_visitado { background: #f3f4f6; color: #4b5563; }
        .alt { font-size: 8.5px; margin: 0; padding-left: 10px; }
        .alt .de { color: #b91c1c; text-decoration: line-through; }
        .alt .para { color: #047857; font-weight: bold; }
        .obs { color: #6b7280; font-style: italic; font-size: 8.5px; }
        .inc { color: #b91c1c; font-size: 8.5px; }
        .rodape { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>

    <h1>Relatório de Validação da Coleta — {{ $tenant?->name }}</h1>
    <div class="meta">
        Campanha: <strong>{{ $campanha }}</strong> ·
        Período: {{ $periodo }} ·
        Coletor: {{ $coletor ?? 'todos' }} ·
        Emitido em {{ $dataHora }}
    </div>

    @if ($validacao)
        <div class="carimbo">
            ✔ CAMPANHA VALIDADA PELA PREFEITURA — por {{ $validacao['nome'] ?? '—' }} em {{ $validacao['validado_em'] ?? '—' }}
        </div>
    @endif

    <table class="cards">
        <tr>
            <td><span class="num">{{ $resumo['total'] }}</span> Coletas no recorte</td>
            <td><span class="num">{{ $resumo['coletados'] }}</span> Coletados</td>
            <td><span class="num">{{ $resumo['pendentes'] }}</span> Pendentes</td>
            <td><span class="num">{{ $resumo['inconformidades'] }}</span> Inconformidades</td>
            <td><span class="num">{{ $resumo['com_alteracoes'] }}</span> Com alterações</td>
            <td><span class="num">{{ $resumo['divergencias'] }}</span> Divergências de proprietário</td>
        </tr>
    </table>

    @if ($divergencias !== [])
        <h2>⚠ Divergências de proprietário apontadas em campo</h2>
        <table class="dados">
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Quadra</th>
                    <th>Inscrição</th>
                    <th>Proprietário oficial</th>
                    <th>Informado na coleta</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($divergencias as $d)
                    <tr>
                        <td><strong>{{ $d['lote'] }}</strong></td>
                        <td>{{ $d['quadra'] }}</td>
                        <td>{{ $d['inscricao'] }}</td>
                        <td>{{ $d['oficial_nome'] }}<br><span class="obs">{{ $d['oficial_cpf_cnpj'] }}</span></td>
                        <td><strong>{{ $d['divergente_nome'] }}</strong><br><span class="obs">{{ $d['divergente_cpf_cnpj'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Coletas realizadas</h2>
    <table class="dados">
        <thead>
            <tr>
                <th style="width: 8%;">Lote</th>
                <th style="width: 10%;">Quadra</th>
                <th style="width: 13%;">Coletor</th>
                <th style="width: 10%;">Quando</th>
                <th style="width: 21%;">Status / Observações</th>
                <th>Alterações (antes → depois)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $l)
                <tr>
                    <td><strong>{{ $l['lote'] }}</strong></td>
                    <td>{{ $l['quadra'] }}</td>
                    <td>{{ $l['coletor'] }}</td>
                    <td>{{ $l['coletado_em'] }}</td>
                    <td>
                        <span class="badge b-{{ $l['status'] }}">{{ $l['status_rotulo'] }}</span>
                        @if ($l['inconformidade'])
                            <div class="inc">{{ $l['inconformidade'] }}
                                @if ($l['inconformidade_gps']) (GPS: {{ $l['inconformidade_gps'] }}) @endif
                            </div>
                        @endif
                        @if ($l['observacao'])
                            <div class="obs">{{ $l['observacao'] }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($l['alteracoes'] === [])
                            —
                        @else
                            <ul class="alt">
                                @foreach ($l['alteracoes'] as $a)
                                    <li>
                                        {{ $a['contexto'] }} · <strong>{{ $a['campo'] }}</strong>:
                                        <span class="de">{{ $a['de'] }}</span> →
                                        <span class="para">{{ $a['para'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; color:#9ca3af;">Nenhuma coleta no recorte selecionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="rodape">SIGWEB · Relatório de Validação da Coleta · {{ $tenant?->name }} · {{ $dataHora }}</div>

</body>
</html>
