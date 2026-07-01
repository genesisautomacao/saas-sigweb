<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title }} #{{ $ordemServico->sequential_id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; word-break: break-word; }
        th { background-color: #eee; font-weight: bold; }
        h1 { font-size: 20px; }
        h2 { font-size: 16px; background-color: #f4f4f4; padding: 5px; margin-top: 20px; page-break-after: avoid; }
        .logo { max-width: 120px; max-height: 70px; }
        .no-border { border: none; }
        .text-right { text-align: right; }
        .vertical-top { vertical-align: top; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    {{-- 1. CABEÇALHO --}}
    <table class="no-border">
        <tr>
            <td class="no-border" style="width: 30%;">
                @if(isset($ordemServico->tenant->data['branding']['logo_path']))
                    <img src="{{ public_path('storage/' . $ordemServico->tenant->data['branding']['logo_path']) }}" class="logo">
                @else
                    <img src="{{ public_path('assets/images/logo.png')}}" alt="Logo" class="logo" />
                @endif
            </td>
            <td class="no-border text-right vertical-top" style="width: 70%;">
                <h1>{{ $title }} #{{ $ordemServico->sequential_id }}</h1>
                <p style="margin: 0;">Data de Emissão: {{ now()->format('d/m/Y H:i') }}</p>
                <p style="margin: 0;">Status da OS: <strong>{{ strtoupper($ordemServico->status) }}</strong></p>
            </td>
        </tr>
    </table>

    {{-- 2. DETALHES DA SOLICITAÇÃO --}}
    <h2>Detalhes da Solicitação de Origem</h2>
    <table>
        <tr>
            <th style="width: 25%;">Alvo da Manutenção:</th>
            <td>
                @if($ordemServico->asset_type === 'App\Models\Poste')
                    💡 Poste #{{ $ordemServico->asset->sequential_id ?? 'N/A' }}
                @elseif($ordemServico->asset_type === 'App\Models\Arvore')
                    🌳 Árvore #{{ $ordemServico->asset->sequential_id ?? 'N/A' }}
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <th>Chamado Base:</th>
            <td>{{ $ordemServico->solicitacao ? 'SM #' . $ordemServico->solicitacao->sequential_id : 'Criada Avulsa (Sem SM)' }}</td>
        </tr>
        <tr>
            <th>Problema Relatado:</th>
            <td>{{ $ordemServico->solicitacao->tipo_servico ?? 'N/A' }} - {{ $ordemServico->solicitacao->observacao ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- 3. MINI-MAPA DE LOCALIZAÇÃO --}}
    @if(!empty($mapImageBase64))
    <h2>Localização do Artefato</h2>
    <div style="text-align: center; border: 1px solid #ccc; padding: 4px; margin-bottom: 15px;">
        <img src="{{ $mapImageBase64 }}" style="width: 400px; height: 300px; object-fit: cover;" alt="Mapa de localização">
        <p style="margin: 3px 0 0; font-size: 9px; color: #777;">
            Mapa de situação (zoom 17) — © OpenStreetMap contributors
        </p>
    </div>
    @endif

    {{-- 5. DETALHES DA ORDEM DE SERVIÇO (OS) --}}
    <h2>Detalhes da Ordem de Serviço</h2>
    <table>
        <tr>
            <th style="width: 25%;">Descrição / Instrução:</th>
            <td>{{ $ordemServico->descricao_servico }}</td>
        </tr>
        <tr>
            <th>Laudo do Técnico:</th>
            <td>{{ $ordemServico->laudo_tecnico ?? 'Ainda não preenchido' }}</td>
        </tr>
    </table>

    {{-- 6. EQUIPE --}}
    <h2>Equipe Designada</h2>
    @if($ordemServico->equipe->isEmpty())
        <p>Nenhuma equipe designada.</p>
    @else
        <table>
            <thead>
                <tr><th style="width: 15%;">ID</th><th>Nome do Colaborador</th></tr>
            </thead>
            <tbody>
                @foreach($ordemServico->equipe as $membro)
                    <tr>
                        <td>#{{ $membro->sequential_id }}</td>
                        <td>{{ $membro->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 7. ITENS (Saída de Estoque) --}}
    <h2>Materiais Utilizados (Estoque)</h2>
    @if($ordemServico->materiais->isEmpty())
        <p>Nenhum material registrado nesta OS.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Produto / Material</th>
                    <th>Local de Origem (Baixa)</th>
                    <th style="width: 15%;">Quantidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ordemServico->materiais as $item)
                    <tr>
                        <td>{{ $item->produto->name ?? 'N/A' }}</td>
                        <td>{{ $item->localEstoque->name ?? 'N/A' }}</td>
                        <td>{{ $item->quantidade }} {{ $item->produto->unit ?? 'UN' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- 8. RODAPÉ --}}
    <div class="footer">
        <p>Documento Oficial - {{ $ordemServico->tenant->name ?? 'Sistema de Gestão' }}</p>
    </div>
</body>
</html>