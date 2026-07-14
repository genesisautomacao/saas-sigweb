<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #374151; font-size: 12px; margin: 0; }
        .header { border-bottom: 1px solid #d1d5db; padding-bottom: 8px; margin-bottom: 18px; }
        .header table { width: 100%; }
        .header-logo { height: 52px; }
        .header-title { font-size: 15px; font-weight: bold; color: #111827; }
        .header-sub { font-size: 10px; color: #9ca3af; }
        .doc-title { font-size: 15px; font-weight: bold; color: #111827; text-align: center;
            text-transform: uppercase; letter-spacing: 0.05em; margin: 10px 0 4px; }
        .doc-proto { font-size: 10px; color: #9ca3af; text-align: center; margin-bottom: 18px; }
        .conteudo { font-size: 12px; line-height: 1.6; color: #111827; text-align: justify; }
        .conteudo p { margin: 0 0 8px; }
        .local-data { margin-top: 28px; text-align: right; }
        .assinatura { margin-top: 60px; text-align: center; }
        .assinatura .linha { border-top: 1px solid #374151; width: 320px; margin: 0 auto 4px; }
        .assinatura .nome { font-weight: bold; }
        .assinatura .cpf { font-size: 10px; color: #6b7280; }
        .assinatura .rotulo { font-size: 10px; color: #9ca3af; margin-top: 2px; }
        .footer { margin-top: 40px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
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
                    <div class="header-sub">Processo Digital — Requerimento</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">Requerimento — {{ $processo->fluxo->nome ?? 'Processo Digital' }}</div>
    <div class="doc-proto">Protocolo <strong>{{ $processo->codigo_processo }}</strong></div>

    <div class="conteudo">{!! $conteudo !!}</div>

    <div class="local-data">{{ $localData }}.</div>

    <div class="assinatura">
        <div class="linha"></div>
        <div class="nome">{{ $requerenteNome ?: 'Requerente' }}</div>
        @if($requerenteCpf)
            <div class="cpf">CPF {{ $requerenteCpf }}</div>
        @endif
        <div class="rotulo">Assinatura do(a) Requerente</div>
    </div>

    <div class="footer">
        Documento gerado eletronicamente pelo SIGWEB em {{ $dataHora }} · Protocolo {{ $processo->codigo_processo }}<br>
        Após assinar, digitalize (ou fotografe de forma legível) e anexe este requerimento no Portal do Cidadão.
    </div>
</body>
</html>
