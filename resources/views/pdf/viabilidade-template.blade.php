<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Consulta de Viabilidade</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #333;
        }

        /* Layout Geral */
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

        .header-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-sub {
            font-size: 10px;
            color: #666;
        }

        /* Limpa Float */
        .clear {
            clear: both;
        }

        /* Caixas de Informação */
        .box {
            border: 1px solid #ccc;
            padding: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }

        .box-title {
            font-weight: bold;
            background-color: #eee;
            padding: 3px;
            border-bottom: 1px solid #ccc;
            margin: -5px -5px 5px -5px;
            font-size: 10px;
            text-transform: uppercase;
        }

        /* Tabela de Dados (Lote) */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .info-table td {
            padding: 3px;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            font-size: 9px;
            color: #555;
            display: block;
        }

        .value {
            font-size: 11px;
            font-weight: bold;
        }

        /* Mapa */
        .map-container {
            width: 100%;
            height: 250px;
            border: 1px solid #000;
            margin-bottom: 10px;
            overflow: hidden;
            text-align: center;
            background: #eee;
        }

        .map-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        /* Tabela de Viabilidade (CNAEs) */
        .viab-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .viab-table th {
            background-color: #444;
            color: #fff;
            padding: 5px;
            text-align: left;
            font-size: 10px;
        }

        .viab-table td {
            border-bottom: 1px solid #ddd;
            padding: 5px;
            vertical-align: middle;
        }

        /* Badges de Status */
        .badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            color: #fff;
            text-align: center;
            display: inline-block;
            min-width: 60px;
        }

        .bg-permitido {
            background-color: #198754;
        }

        /* Verde */
        .bg-permissivel {
            background-color: #ffc107;
            color: #000;
        }

        /* Amarelo */
        .bg-proibido {
            background-color: #dc3545;
        }

        /* Vermelho */

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            text-align: right;
        }
    </style>
</head>

<body>

    {{-- HEADER --}}
    <div class="header">
        {{-- Tenta pegar logo do tenant, senão usa um placeholder --}}
        @if(isset($tenant->data['logo']))
            <img src="{{ public_path('storage/' .$tenant->data['logo']) }}" class="header-logo">
        @else
            {{-- Substitua pelo caminho do seu brasão padrão em public/ --}}
            <img src="{{ public_path('assets/images/logo-login.png')}}" alt="Logo" class="header-logo" />
        @endif

        <div class="header-text">
            <div class="header-title">{{ $tenant->name ?? 'Prefeitura Municipal' }}</div>
            <div class="header-sub">Secretaria de Planejamento e Urbanismo</div>
            <div style="margin-top: 5px; font-size: 14px;">Relatório de Viabilidade de Uso do Solo</div>
        </div>
        <div class="clear"></div>
    </div>

    {{-- DADOS DO IMÓVEL --}}
    <div class="box">
        <div class="box-title">Identificação do Imóvel</div>
        <table class="info-table">
            <tr>
                <td width="20%">
                    <span class="label">Número do Lote</span>
                    <span class="value">{{ $dadosAnalise['numero_lote'] }}</span>
                </td>
                <td width="50%">
                    <span class="label">Zona Identificada</span>
                    <span class="value">{{ $dadosAnalise['zona']['sigla'] }} -
                        {{ $dadosAnalise['zona']['nome'] }}</span>
                </td>
                <td width="30%" style="text-align: right;">
                    <span class="label">Protocolo</span>
                    <span class="value">{{ $protocolo }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- MAPA --}}
    @if($mapImage)
        <div class="box">
            <div class="box-title">Localização Espacial</div>
            <div class="map-container">
                <img src="{{ $mapImage }}" class="map-img">
            </div>
            <div style="font-size: 9px; text-align: center; color: #666; margin-top: 2px;">
                Imagem gerada em {{ $dataHora }}
            </div>
        </div>
    @endif

    {{-- RESULTADO DA ANÁLISE --}}
    <div style="margin-top: 15px;">
        <h3 style="border-bottom: 2px solid #ccc; padding-bottom: 5px; margin-bottom: 10px;">Análise de Atividades
            Econômicas</h3>

        <table class="viab-table">
            <thead>
                <tr>
                    <th width="15%">CNAE</th>
                    <th width="50%">Descrição da Atividade</th>
                    <th width="20%">Classificações</th>
                    <th width="15%" style="text-align: center;">Parecer</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dadosAnalise['analises'] as $item)
                    <tr>
                        <td style="font-weight: bold;">{{ $item['cnae'] }}</td>
                        <td>{{ $item['descricao'] }}</td>

                        {{-- Detalhes das Classificações --}}
                        <td style="font-size: 9px;">
                            @foreach($item['classificacoes_detalhe'] as $detalhe)
                                <div>
                                    <strong>{{ $detalhe['classificacao'] }}:</strong>
                                    {{ ucfirst($detalhe['status']) }}
                                </div>
                            @endforeach
                        </td>

                        {{-- Status Final (Semáforo) --}}
                        <td style="text-align: center;">
                            @php
                                $class = match ($item['status_final']) {
                                    'permitido' => 'bg-permitido',
                                    'permissivel' => 'bg-permissivel',
                                    'proibido' => 'bg-proibido',
                                    default => 'bg-proibido'
                                };
                            @endphp
                            <span class="badge {{ $class }}">
                                {{ strtoupper($item['status_final']) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- OBSERVAÇÕES LEGAIS --}}
    <div
        style="margin-top: 20px; border-top: 1px dashed #999; padding-top: 10px; font-size: 0.55rem; text-align: justify; color: #555;">
        <strong>Observações Importantes:</strong><br>
        1. Este documento é apenas uma consulta prévia de viabilidade e NÃO substitui o Alvará de Funcionamento.<br>
        2. A aprovação final depende da verificação de outros requisitos legais, sanitários e ambientais.<br>
        3. As informações aqui constantes baseiam-se na Lei de Uso e Ocupação do Solo vigente nesta data.<br> <br>

        * Consideram-se usos permitidos aqueles compatíveis com a vocação da zona conforme sua realidade urbanística e
        socioambiental. <br> <br>

        * Consideram-se usos permissíveis aqueles admitidos na respectiva zona, desde que comprovada a compatibilidade
        com os usos permitidos, mediante observância das seguintes condições: <br>

        I. Apresentação, pelo interessado e às suas expensas, de Estudo de Impacto de Vizinhança (EIV), elaborado e
        assinado por profissional habilitado, submetido à análise e emissão de parecer técnico prévio pelo Conselho
        de Desenvolvimento Municipal - CDM; <br>

        II. Aprovação do referido estudo pela Secretaria de Planejamento, mediante
        parecer técnico favorável emitido por maioria simples; <br>

        III. Obtenção da anuência expressa de, no mínimo, setenta e cinco por cento (75%) de, pelo menos, 8 (oito)
        vizinhos lindeiros e imediatos ao imóvel objeto da solicitação. <br> <br>


        * Consideram-se usos proibidos aqueles vedados em qualquer hipótese na respectiva zona, em razão de sua
        incompatibilidade com os objetivos e diretrizes desta Lei.
        
    </div>

    {{-- BLOCO DE AUTENTICAÇÃO (TR Tangará #21 / #14) --}}
    <div style="margin-top: 24px; padding: 12px; border: 1.5px dashed #1f2937; border-radius: 6px; font-size: 10px; line-height: 1.5; background: #f9fafb;">
        <div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">Autenticação do Documento</div>
        <div><strong>Código:</strong> {{ $protocolo }}</div>
        <div><strong>Validar em:</strong> {{ $urlValidacao ?? url('/v/'.$protocolo) }}</div>
        <div style="color: #6b7280; margin-top: 4px;">Este documento foi emitido eletronicamente. Para conferir sua autenticidade, acesse o endereço acima ou informe o código no portal da Prefeitura.</div>
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        Gerado via Sistema SIGWEB | Data: {{ $dataHora }} | Operador: {{ auth()->user()->name ?? 'Sistema' }}
    </div>

</body>

</html>