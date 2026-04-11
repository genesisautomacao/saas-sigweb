<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Estudo de Parcelamento do Solo</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #333;
        }

        .container {
            width: 100%;
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header-logo {
            width: 60px;
            height: auto;
            float: left;
            margin-right: 15px;
        }

        .logo {
            max-height: 60px;
            width: auto;
            float: left;
            margin-right: 15px;
        }

        .header-text {
            float: left;
            margin-top: 5px;
        }

        .clear {
            clear: both;
        }

        .box {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .box h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table th,
        .info-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }

        .info-table th {
            background-color: #f5f5f5;
            width: 25%;
            font-weight: bold;
        }

        .map-container {
            text-align: center;
            margin-bottom: 15px;
        }

        .map-image {
            max-width: 100%;
            max-height: 350px;
            border: 1px solid #999;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            color: #777;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .status {
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            @if (isset($tenant->data['logo']))
                <img src="{{ public_path('storage/' . $tenant->data['logo']) }}" class="header-logo">
            @else
                {{-- Substitua pelo caminho do seu brasão padrão em public/ --}}
                <img src="{{ public_path('assets/images/logo-login.png') }}" alt="Logo" class="header-logo" />
            @endif
            <div class="header-text">
                <div style="font-size: 16px; font-weight: bold;">PREFEITURA MUNICIPAL</div>
                <div style="font-size: 10px; color: #666;">Secretaria de Planejamento e Desenvolvimento Urbano</div>
                <div style="margin-top: 5px; font-size: 14px; font-weight: bold;">ESTUDO DE PARCELAMENTO DO SOLO</div>
            </div>
            <div class="clear"></div>
        </div>

        <div class="box">
            <h3>Identificação do Imóvel</h3>
            <table class="info-table">
                <tr>
                    <th>Protocolo:</th>
                    <td>{{ $protocolo }}</td>
                    <th>Data da Emissão:</th>
                    <td>{{ $dataHora }}</td>
                </tr>
                <tr>
                    <th>Lote:</th>
                    <td>{{ $dadosAnalise['numero_lote'] ?? 'S/N' }}</td>
                    <th>Zona:</th>
                    <td>{{ $dadosAnalise['zona']['sigla'] ?? '-' }} - {{ $dadosAnalise['zona']['nome'] ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="box">
            <h3>Análise Geométrica</h3>
            <table class="info-table" style="text-align: center;">
                <tr>
                    <td style="width: 33%;">
                        <strong>Lote Original</strong><br>
                        <div style="margin-top: 5px;">
                            Área: <span
                                style="font-size: 13px;">{{ number_format($dadosAnalise['area_base_m2'], 2, ',', '.') }}
                                m²</span><br>
                            Face: <span
                                style="font-size: 13px;">{{ number_format($dadosAnalise['testada_base_m'] ?? 0, 2, ',', '.') }}
                                m</span>
                        </div>
                    </td>
                    <td style="width: 33%; vertical-align: middle;">
                        <strong>Divisão Solicitada</strong><br>
                        <span style="font-size: 14px;">{{ $dadosAnalise['qtd_lotes'] }} Frações</span>
                    </td>
                    <td style="width: 33%;">
                        <strong>Estimativa Resultante</strong><br>
                        <div style="margin-top: 5px;">
                            Área: <span
                                style="font-size: 13px; color: #3b82f6; font-weight: bold;">{{ number_format($dadosAnalise['area_fracionada_m2'] ?? 0, 2, ',', '.') }}
                                m²</span><br>
                            Face: <span
                                style="font-size: 13px; color: #3b82f6; font-weight: bold;">{{ number_format($dadosAnalise['testada_fracionada_m'] ?? 0, 2, ',', '.') }}
                                m</span>
                        </div>
                    </td>
                </tr>
            </table>

            <h3>Parecer Técnico Preliminar</h3>
            <div
                style="background-color: #f9fafb; padding: 10px; border-left: 4px solid #3b82f6; margin-bottom: 10px; font-size: 12px; line-height: 1.5;">
                @foreach ($dadosAnalise['parecer_tecnico'] as $parecer)
                    <p style="margin: 0 0 5px 0;">{{ $parecer }}</p>
                @endforeach
            </div>

            <table class="info-table" style="margin-top: 10px;">
                <tr>
                    <th>Veredito Oficial:</th>
                    <td>
                        @php
                            $st = $dadosAnalise['status_final'] ?? 'proibido';
                            $bg = $st === 'permitido' ? '#10b981' : ($st === 'permissivel' ? '#f59e0b' : '#ef4444');
                        @endphp
                        <span class="status"
                            style="background-color: {{ $bg }}">{{ strtoupper($st) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        @if ($mapImage)
            <div class="box">
                <h3>Croqui de Localização</h3>
                <div class="map-container">
                    <img src="{{ $mapImage }}" class="map-image">
                </div>
            </div>
        @endif

        <div class="footer">
            Gerado via Sistema SIGWEB | Data: {{ $dataHora }} | Operador: {{ auth()->user()->name ?? 'Sistema' }}
        </div>
    </div>
</body>

</html>
