<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Memorial Descritivo</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #333; margin: 30px 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .logo-container { text-align: center; margin-bottom: 20px; }
        .brand-image { max-height: 80px; }
        .header h1 { font-size: 18px; text-transform: uppercase; margin: 0 0 5px 0; }
        .header h2 { font-size: 14px; font-weight: normal; margin: 0; color: #555; }
        .title { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 25px; text-transform: uppercase; text-decoration: underline; }
        .content { text-align: justify; margin-bottom: 30px; }
        .content p { text-indent: 30px; margin-top: 10px; }
        .dados-lote { background-color: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; }
        .table-container { margin-bottom: 30px; page-break-inside: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
        th, td { border: 1px solid #bbb; padding: 6px; text-align: center; }
        th { background-color: #f0f0f0; font-weight: bold; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #fafafa; }
        .footer { margin-top: 50px; text-align: center; page-break-inside: avoid; }
        .assinatura { width: 300px; border-top: 1px solid #000; margin: 50px auto 10px auto; padding-top: 5px; }
        .data { text-align: right; margin-top: 30px; font-style: italic; }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo-container">
           @php
               // Pega a Tenant ativa de forma nativa no Filament v3
               $tenant = \Filament\Facades\Filament::getTenant();

               // No futuro, quando você adicionar uma coluna 'logo' na sua tabela de tenants, é só ajustar aqui:
               $logoPath = $tenant ? data_get($tenant, 'data.logo') : null;
           @endphp

           @if ($logoPath && file_exists(public_path('storage/' . $logoPath)))
                <img src="{{ public_path('storage/' . $logoPath) }}" alt="Logo" class="brand-image" />
            @else
                <h2 style="color: #666;">{{ $tenant ? $tenant->name : 'SaaS Base' }}</h2>
            @endif
        </div>

        <h1>{{ $tenantNome }}</h1>
        <h2>Secretaria de Planejamento e Desenvolvimento Urbano</h2>
        <h2>Sistema de Gestão Cartográfica e Zeladoria (SIGWEB)</h2>
    </div>

    <div class="title">
        Memorial Descritivo de Perímetro
    </div>

    <div class="dados-lote">
        <strong>Identificação do Lote:</strong> {{ $lote->numero_lote ?? 'S/N' }}<br>
        <strong>Área Total Aproximada:</strong> {{ number_format($lote->area_geo, 2, ',', '.') }} m²<br>
        <strong>Código no Sistema (ID):</strong> #{{ $lote->id }}
    </div>

    <div class="content">
        <strong>DESCRIÇÃO DO PERÍMETRO:</strong>
        <p>{{ $textoMemorial }}</p>
    </div>

    <div class="table-container">
        <strong>TABELA DE VÉRTICES E SEGMENTOS:</strong>
        <table>
            <thead>
                <tr>
                    <th>Segmento</th>
                    <th>Distância (m)</th>
                    <th>Azimute</th>
                    <th>Coordenadas UTM (E, N)</th>
                    <th>Confrontante</th>
                </tr>
            </thead>
            <tbody>
                @foreach($segmentos as $seg)
                    <tr>
                        <td>V{{ $loop->iteration }} - V{{ $loop->last ? 1 : $loop->iteration + 1 }}</td>
                        <td>{{ number_format($seg->distancia, 2, ',', '.') }}</td>
                        <td>{{ number_format($seg->azimute, 2, ',', '.') }}°</td>
                        <td class="text-center">
                            E: {{ number_format($seg->start_e, 2, ',', '.') }}<br>
                            N: {{ number_format($seg->start_n, 2, ',', '.') }}
                        </td>
                        <td>
                            @if($seg->confrontante_logradouro)
                                Rua {{ $seg->confrontante_logradouro }}
                            @elseif($seg->confrontante_lote)
                                Lote {{ $seg->confrontante_lote }}
                            @else
                                Área Não Cadastrada
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="data">
        Documento gerado eletronicamente em {{ $dataExtenso }}.
    </div>

    <div class="footer">
        <div class="assinatura">
            <strong>Responsável Técnico / Setor de Engenharia</strong><br>
            {{ $tenantNome }}
        </div>
    </div>

</body>
</html>