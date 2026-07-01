<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Relatório de Lotes' }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 12px; }
        .table-container { margin: auto; width: 95%; }
        h1 { text-align: center; margin-bottom: 20px; font-size: 22px; }
        .lote-block { margin-bottom: 16px; border: 1px solid #999; padding: 8px; page-break-inside: avoid; }
        table.lote-header { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.lote-header td { border: none; padding: 2px 6px; font-size: 12px; }
        table.sub-table { width: 100%; border-collapse: collapse; margin: 4px 0 8px 0; }
        table.sub-table th, table.sub-table td { border: 1px solid #ddd; padding: 4px; font-size: 10px; text-align: left; }
        table.sub-table thead tr { background-color: #f2f2f2; }
        .sub-title { font-size: 11px; font-weight: bold; margin: 6px 0 2px 0; color: #555; }
        .logo-container { text-align: center; margin-bottom: 20px; }
        .brand-image { max-height: 80px; }
        .empty { color: #999; font-style: italic; font-size: 10px; }
    </style>
</head>
<body>
    <div class="table-container">
        <div class="logo-container">
            @php
                $tenant = \Filament\Facades\Filament::getTenant();
                $logoPath = $tenant ? data_get($tenant, 'data.logo') : null;
            @endphp
            @if ($logoPath && file_exists(public_path('storage/' . $logoPath)))
                <img src="{{ public_path('storage/' . $logoPath) }}" alt="Logo" class="brand-image" />
            @else
                <h2 style="color: #666; text-align:center;">{{ $tenant ? $tenant->name : 'SaaS Base' }}</h2>
            @endif
        </div>

        <h1>{{ $title ?? 'Relatório de Lotes' }}</h1>

        @forelse($lotes as $lote)
            <div class="lote-block">
                <table class="lote-header">
                    <tr>
                        <td><strong>ID:</strong> {{ $lote->sequential_id }}</td>
                        <td><strong>Lote nº:</strong> {{ $lote->numero_lote }}</td>
                        <td><strong>Quadra:</strong> {{ $lote->quadra->name ?? '-' }}</td>
                        <td><strong>Zona:</strong> {{ $lote->zona->sigla ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Testada (m):</strong> {{ $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00' }}</td>
                        <td colspan="3"><strong>Área Geo (m²):</strong> {{ $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00' }}</td>
                    </tr>
                </table>

                <div class="sub-title">Unidades Imobiliárias</div>
                @if($lote->unidadesImobiliarias->isNotEmpty())
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th>Código Tributário</th>
                                <th>Inscrição</th>
                                <th>Logradouro</th>
                                <th>Número</th>
                                <th>Proprietário</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lote->unidadesImobiliarias as $unidade)
                                <tr>
                                    <td>{{ $unidade->codigo_imovel_tributario ?? '-' }}</td>
                                    <td>{{ $unidade->inscricao_imobiliaria ?? '-' }}</td>
                                    <td>{{ $unidade->logradouro_nome ?? '-' }}</td>
                                    <td>{{ $unidade->numero_imovel ?? '-' }}</td>
                                    <td>{{ $unidade->proprietario->name ?? ($unidade->dados_tributarios['proprietario_name'] ?? '-') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="empty">Nenhuma unidade imobiliária vinculada.</p>
                @endif

                <div class="sub-title">Edificações</div>
                @if($lote->edificacoes->isNotEmpty())
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Tipo de Construção</th>
                                <th>Estado de Conservação</th>
                                <th>Área (m²)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lote->edificacoes as $edificacao)
                                <tr>
                                    <td>{{ $edificacao->tipo ?? '-' }}</td>
                                    <td>{{ $edificacao->tp_construcao ?? '-' }}</td>
                                    <td>{{ $edificacao->estado_conservacao ?? '-' }}</td>
                                    <td>{{ $edificacao->area_geo ? number_format($edificacao->area_geo, 2, ',', '') : '0,00' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="empty">Nenhuma edificação vinculada.</p>
                @endif
            </div>
        @empty
            <p style="text-align:center;">Nenhum lote para exibir.</p>
        @endforelse
    </div>
</body>
</html>
