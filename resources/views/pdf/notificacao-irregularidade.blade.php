<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Notificação de Irregularidade</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { border: none; padding: 0; vertical-align: middle; }
        .logo { max-width: 110px; max-height: 65px; }
        .municipio-nome { font-size: 17px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
        .municipio-sub { font-size: 10px; color: #555; margin-top: 2px; }
        .doc-titulo {
            text-align: center;
            margin: 25px 0 10px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #b91c1c;
        }
        .doc-subtitulo {
            text-align: center;
            font-size: 11px;
            color: #555;
            margin-bottom: 25px;
        }
        .section-title {
            background: #f1f5f9;
            border-left: 4px solid #1e3a8a;
            padding: 5px 8px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            margin: 18px 0 8px;
            color: #1e3a8a;
        }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.dados td { padding: 5px 7px; border: 1px solid #ddd; font-size: 11px; vertical-align: top; }
        table.dados td.label { background: #f8fafc; font-weight: bold; width: 30%; color: #444; }
        .corpo { line-height: 1.7; text-align: justify; margin: 0 0 12px; font-size: 12px; }
        .irregularidade-box {
            border: 1px solid #fca5a5;
            background: #fff7f7;
            padding: 12px 15px;
            border-radius: 4px;
            margin: 12px 0;
            font-size: 12px;
            line-height: 1.6;
        }
        .prazo-box {
            border: 2px solid #b91c1c;
            background: #fef2f2;
            padding: 10px 14px;
            border-radius: 4px;
            margin: 15px 0;
            font-weight: bold;
            color: #b91c1c;
            font-size: 12px;
            text-align: center;
        }
        .assinatura { margin-top: 50px; }
        .assinatura table { width: 100%; border-collapse: collapse; }
        .assinatura td { border: none; text-align: center; padding: 0 20px; font-size: 11px; }
        .assinatura .linha { border-top: 1px solid #333; width: 80%; margin: 0 auto 5px; }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    {{-- CABEÇALHO --}}
    <div class="header">
        <table>
            <tr>
                <td style="width: 20%;">
                    @if(isset($tenant->data['branding']['logo_path']))
                        <img src="{{ public_path('storage/' . $tenant->data['branding']['logo_path']) }}" class="logo">
                    @else
                        <img src="{{ public_path('assets/images/logo.png') }}" class="logo" alt="Logo">
                    @endif
                </td>
                <td>
                    <div class="municipio-nome">{{ $tenant->name ?? 'Prefeitura Municipal' }}</div>
                    <div class="municipio-sub">Sistema de Gestão Territorial — SIGWEB</div>
                </td>
                <td style="width: 20%; text-align: right; font-size: 10px; color: #555;">
                    <strong>Emitido em:</strong><br>{{ $dataHora }}
                </td>
            </tr>
        </table>
    </div>

    {{-- TÍTULO --}}
    <div class="doc-titulo">Notificação de Irregularidade</div>
    <div class="doc-subtitulo">Documento Oficial — Fiscalização Urbana</div>

    {{-- DADOS DO IMÓVEL --}}
    <div class="section-title">Imóvel Notificado</div>
    <table class="dados">
        <tr>
            <td class="label">Número do Lote:</td>
            <td>{{ $lote->numero_lote }}</td>
            <td class="label">Quadra:</td>
            <td>{{ $lote->quadra?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Loteamento:</td>
            <td>{{ $lote->quadra?->loteamento?->name ?? '—' }}</td>
            <td class="label">Bairro:</td>
            <td>{{ $lote->quadra?->loteamento?->bairro?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Endereço:</td>
            <td colspan="3">{{ $enderecoLote }}</td>
        </tr>
    </table>

    {{-- DADOS DO NOTIFICADO --}}
    <div class="section-title">Notificado</div>
    <table class="dados">
        <tr>
            <td class="label">Proprietário / Responsável:</td>
            <td>{{ $proprietario }}</td>
        </tr>
    </table>

    {{-- CORPO DA NOTIFICAÇÃO --}}
    <div class="section-title">Irregularidade Constatada</div>
    <p class="corpo">
        Em vistoria realizada pela fiscalização deste município, foi constatada a seguinte
        irregularidade no imóvel acima identificado:
    </p>
    <div class="irregularidade-box">
        {{ $lote->inconformidade_descricao }}
    </div>

    {{-- PRAZO --}}
    <div class="prazo-box">
        PRAZO PARA REGULARIZAÇÃO: 30 (TRINTA) DIAS A CONTAR DO RECEBIMENTO DESTA NOTIFICAÇÃO
    </div>

    {{-- FUNDAMENTAÇÃO --}}
    <p class="corpo">
        O não cumprimento das medidas exigidas no prazo estipulado sujeitará o responsável às
        penalidades previstas no Código de Obras e Posturas do Município, sem prejuízo das demais
        sanções administrativas e legais cabíveis.
    </p>

    {{-- ASSINATURA --}}
    <div class="assinatura">
        <p style="margin-bottom: 5px;">{{ $tenant->name ?? 'Prefeitura Municipal' }}, {{ $dataExtenso }}.</p>
        <table>
            <tr>
                <td>
                    <div class="linha"></div>
                    Fiscal Responsável
                </td>
                <td>
                    <div class="linha"></div>
                    {{ $proprietario }}<br>Notificado
                </td>
            </tr>
        </table>
    </div>

    {{-- RODAPÉ --}}
    <div class="footer">
        Documento emitido pelo SIGWEB — {{ $tenant->name ?? 'Prefeitura Municipal' }} — {{ $dataHora }}
    </div>

</body>
</html>
