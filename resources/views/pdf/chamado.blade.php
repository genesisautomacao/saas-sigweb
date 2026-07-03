<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 0; }
        .header { border-bottom: 3px solid #073bc0; padding-bottom: 8px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 18px; color: #073bc0; }
        .header .protocolo { font-size: 13px; color: #374151; margin-top: 2px; }
        .sec { margin-bottom: 14px; }
        .sec h2 { font-size: 13px; background: #f3f4f6; padding: 5px 8px; margin: 0 0 6px; border-left: 4px solid #073bc0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; }
        .grid td { width: 50%; }
        .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .val { font-weight: bold; }
        .badge { background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 6px; font-size: 11px; }
        .msg { border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px 8px; margin-bottom: 6px; }
        .msg .meta { color: #6b7280; font-size: 10px; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px 8px; }
        .hist { border-bottom: 1px solid #f3f4f6; padding: 4px 0; }
        img.mapa { width: 400px; height: 300px; border: 1px solid #d1d5db; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Solicitação / Chamado</h1>
        <div class="protocolo">Protocolo: <b>{{ $chamado->protocolo ?? ('#'.$chamado->id) }}</b>
            &nbsp;·&nbsp; Aberto em {{ optional($chamado->created_at)->format('d/m/Y H:i') }}</div>
    </div>

    <div class="sec">
        <h2>Dados do Chamado</h2>
        <table class="grid">
            <tr>
                <td><div class="label">Categoria</div><div class="val">{{ $chamado->categoria->nome ?? '—' }}</div></td>
                <td><div class="label">Fluxo de Trabalho</div><div class="val">{{ $chamado->fluxo->nome ?? '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Fase Atual</div><div class="val"><span class="badge">{{ $chamado->faseAtual->nome ?? '—' }}</span></div></td>
                <td><div class="label">Status</div><div class="val">{{ strtoupper($chamado->status ?? '—') }}</div></td>
            </tr>
        </table>
        <div style="margin-top:6px">
            <div class="label">Descrição</div>
            <div class="box">{{ $chamado->descricao ?: '—' }}</div>
        </div>
    </div>

    <div class="sec">
        <h2>Solicitante</h2>
        <table class="grid">
            <tr>
                <td><div class="label">Nome</div><div class="val">{{ $chamado->solicitante_nome ?: '—' }}</div></td>
                <td><div class="label">Telefone</div><div class="val">{{ $chamado->solicitante_telefone ?: '—' }}</div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="label">E-mail</div><div class="val">{{ $chamado->solicitante_email ?: '—' }}</div></td>
            </tr>
        </table>
    </div>

    @if ($mapa)
        <div class="sec">
            <h2>Localização</h2>
            <img class="mapa" src="{{ $mapa }}" alt="Mapa de localização">
            <div style="font-size:10px;color:#6b7280;margin-top:2px">Base cartográfica: OpenStreetMap.</div>
        </div>
    @endif

    @if (!empty($chamado->respostas_boletim))
        <div class="sec">
            <h2>Respostas do Boletim / Questionário</h2>
            <table>
                @foreach ($chamado->respostas_boletim as $pergunta => $resposta)
                    <tr>
                        <td style="width:40%"><b>{{ is_string($pergunta) ? $pergunta : ('Campo '.($pergunta+1)) }}</b></td>
                        <td>{{ is_array($resposta) ? implode(', ', $resposta) : $resposta }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="sec">
        <h2>Mensagens Públicas</h2>
        @forelse ($mensagensPublicas as $m)
            <div class="msg">
                <div class="meta">{{ $m->remetente->name ?? 'Prefeitura' }} · {{ optional($m->created_at)->format('d/m/Y H:i') }}</div>
                <div>{{ $m->texto }}</div>
            </div>
        @empty
            <div style="color:#6b7280">Nenhuma mensagem pública.</div>
        @endforelse
    </div>

    <div class="sec">
        <h2>Histórico de Alteração de Fases</h2>
        @forelse ($historico as $h)
            <div class="hist">
                <b>{{ $h->fase->nome ?? '—' }}</b>
                <span style="color:#6b7280">— {{ optional($h->created_at)->format('d/m/Y H:i') }}
                    @if ($h->usuario) por {{ $h->usuario->name }} @endif</span>
            </div>
        @empty
            <div style="color:#6b7280">Sem histórico de fases.</div>
        @endforelse
    </div>
</body>
</html>
